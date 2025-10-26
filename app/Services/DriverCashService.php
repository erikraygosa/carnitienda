<?php
namespace App\Services;

use App\Models\DriverCashRegister;
use App\Models\DriverCashMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DriverCashService
{
    /** Obtiene o abre el corte del día para el chofer */
    public function getOrOpenRegister(int $driverId, ?string $fecha = null, float $saldoInicial = 0): DriverCashRegister
    {
        $fecha = $fecha ? Carbon::parse($fecha)->toDateString() : now()->toDateString();

        return DB::transaction(function () use ($driverId,$fecha,$saldoInicial) {
            $reg = DriverCashRegister::firstOrCreate(
                ['driver_id'=>$driverId, 'fecha'=>$fecha],
                [
                    'saldo_inicial'=>$saldoInicial,
                    'opened_at'=>now(),
                    'opened_by'=>Auth::id(),
                    'estatus'=>'ABIERTO'
                ]
            );
            return $reg;
        });
    }

    /** Registra un movimiento (CARGO/ABONO/AJUSTE) y actualiza totales */
    public function addMovement(DriverCashRegister $reg, string $tipo, float $monto, ?string $desc = null, $source = null): DriverCashMovement
    {
        return DB::transaction(function () use ($reg,$tipo,$monto,$desc,$source) {
            $mov = new DriverCashMovement([
                'driver_id'  => $reg->driver_id,
                'tipo'       => $tipo,
                'monto'      => $monto,
                'descripcion'=> $desc,
                'created_by' => auth()->id()
            ]);

            if ($source) {
                $mov->source()->associate($source);
            }

            $reg->movements()->save($mov);

            // actualizar acumulados
            if ($tipo === 'CARGO')  $reg->saldo_cargos += $monto;
            if ($tipo === 'ABONO')  $reg->saldo_abonos += $monto;
            if ($tipo === 'AJUSTE') {
                // por simplicidad, tratamos AJUSTE positivo como ABONO, negativo como CARGO
                if ($monto >= 0) $reg->saldo_abonos += $monto; else $reg->saldo_cargos += abs($monto);
            }

            $reg->saldo_final = ($reg->saldo_inicial + $reg->saldo_cargos) - $reg->saldo_abonos;
            $reg->save();

            return $mov;
        });
    }

    /** Cierra el corte (opcionalmente exigir saldo 0) */
    public function close(DriverCashRegister $reg, bool $requireZero = false): void
    {
        if ($requireZero && (float)$reg->saldo_final !== 0.0) {
            abort(422, 'No puedes cerrar: el saldo final no es cero.');
        }

        $reg->update([
            'estatus'  => 'CERRADO',
            'closed_at'=> now(),
            'closed_by'=> auth()->id(),
        ]);
    }
}
