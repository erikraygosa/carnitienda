<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StampBillingConfig;
use App\Models\StampCut;
use Carbon\Carbon;

/**
 * Cobro de timbres por periodos (cortes). La cortesía se renueva en cada
 * periodo; el consumo se calcula siempre desde las facturas timbradas
 * (fecha de certificación), así el corte se puede auditar.
 */
class StampBillingService
{
    /** Periodo [inicio, fin] que contiene $fecha, según el día de corte. */
    public function periodoQueContiene(StampBillingConfig $cfg, Carbon $fecha): array
    {
        $dia    = max(1, min(28, (int) $cfg->dia_corte));
        $inicio = $fecha->copy()->startOfDay()->day($dia);
        if ($fecha->day < $dia) {
            $inicio = $inicio->subMonthNoOverflow()->day($dia);
        }
        $fin = $inicio->copy()->addMonthNoOverflow()->day($dia)->subDay();

        return [$inicio, $fin];
    }

    /** Todos los periodos desde el inicio de cobro hasta el actual (el más reciente primero). */
    public function periodos(StampBillingConfig $cfg): array
    {
        $desde = $cfg->inicio_cobro ? Carbon::parse($cfg->inicio_cobro) : now();
        [$inicio] = $this->periodoQueContiene($cfg, $desde);
        $hoy = now()->startOfDay();

        $out = [];
        while ($inicio->lte($hoy)) {
            [$i, $f] = $this->periodoQueContiene($cfg, $inicio);
            $out[] = [$i->copy(), $f->copy()];
            $inicio = $f->copy()->addDay();
        }

        return array_reverse($out);
    }

    /** Consumo real de un periodo. */
    public function consumo(int $companyId, Carbon $inicio, Carbon $fin): array
    {
        $q = Invoice::where('company_id', $companyId)
            ->whereNotNull('uuid')
            ->whereBetween(\DB::raw('COALESCE(fecha_timbrado, fecha)'), [
                $inicio->copy()->startOfDay(), $fin->copy()->endOfDay(),
            ]);

        return [
            'usados'     => (clone $q)->count(),
            'cancelados' => (clone $q)->where('estatus', 'CANCELADA')->count(),
        ];
    }

    /** Importes del periodo con la configuración vigente (sin guardar). */
    public function calcular(StampBillingConfig $cfg, Carbon $inicio, Carbon $fin): array
    {
        $c         = $this->consumo($cfg->company_id, $inicio, $fin);
        $excedente = max(0, $c['usados'] - (int) $cfg->cortesia_mensual);
        $importe   = round($excedente * (float) $cfg->precio_timbre, 2);

        return [
            'cortesia'          => (int) $cfg->cortesia_mensual,
            'timbres_usados'    => $c['usados'],
            'cancelados'        => $c['cancelados'],
            'excedente'         => $excedente,
            'precio_timbre'     => (float) $cfg->precio_timbre,
            'mensualidad'       => (float) $cfg->mensualidad,
            'importe_excedente' => $importe,
            'total'             => round($importe + (float) $cfg->mensualidad, 2),
        ];
    }

    /** Genera (o regresa el ya generado) el corte de un periodo terminado. */
    public function generarCorte(StampBillingConfig $cfg, Carbon $inicio, Carbon $fin, ?int $userId = null): StampCut
    {
        $existente = StampCut::where('company_id', $cfg->company_id)
            ->whereDate('periodo_inicio', $inicio->toDateString())->first();
        if ($existente) {
            return $existente;
        }

        $datos = $this->calcular($cfg, $inicio, $fin);

        return StampCut::create($datos + [
            'company_id'     => $cfg->company_id,
            'folio'          => sprintf('CT-%d-%s', $cfg->company_id, $inicio->format('Ymd')),
            'periodo_inicio' => $inicio->toDateString(),
            'periodo_fin'    => $fin->toDateString(),
            'estado'         => 'pendiente',
            'generado_por'   => $userId,
        ]);
    }
}
