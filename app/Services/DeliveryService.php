<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DeliveryService
{
    public function approve($model)
    {
        return $this->transition($model, 'APROBADO');
    }

    public function startPreparing($model)
    {
        $this->transition($model, 'PREPARANDO', ['preparado_at' => now()]);
        return $model;
    }

    public function markProcessed($model)
    {
        $this->transition($model, 'PROCESADO', ['despachado_at' => now()]);
        return $model;
    }

    public function dispatchToRoute($model)
    {
        $this->transition($model, 'EN_RUTA', ['en_ruta_at' => now()]);
        return $model;
    }

    public function markDelivered($model)
    {
        $this->transition($model, 'ENTREGADO', ['entregado_at' => now()]);
        return $model;
    }

    public function markNotDelivered($model, string $note = null)
    {
        $data = ['no_entregado_at' => now()];
        if ($note) $data['delivery_notes'] = trim(($model->delivery_notes ?? '')."\n".$note);
        $this->transition($model, 'NO_ENTREGADO', $data);
        $model->increment('delivery_attempts');
        return $model;
    }

    public function cancel($model, string $note = null)
    {
        $data = [];
        if ($note) $data['delivery_notes'] = trim(($model->delivery_notes ?? '')."\nCANCELADO: ".$note);
        $this->transition($model, 'CANCELADO', $data);
        return $model;
    }

    public function recordCashCollection($model, float $monto)
    {
        DB::transaction(function () use ($model, $monto) {
            $model->cobrado_efectivo = round(($model->cobrado_efectivo ?? 0) + $monto, 2);
            $model->cobrado_confirmado_at = now();
            $model->cobrado_confirmado_por = Auth::id();
            // Si cobró todo, mantenemos PENDIENTE hasta liquidación final
            $model->save();
        });
        return $model;
    }

    public function settleDriver($model, $posRegisterId = null)
    {
        DB::transaction(function () use ($model, $posRegisterId) {
            if ($posRegisterId) $model->pos_register_id = $posRegisterId;
            $model->driver_settlement_status = 'LIQUIDADO';
            $model->driver_settlement_at = now();
            $model->save();
        });
        return $model;
    }

    private function transition($model, string $toStatus, array $extra = [])
    {
        // Validaciones simples de máquina de estados
        $allowed = [
            'BORRADOR'   => ['APROBADO','CANCELADO'],
            'APROBADO'   => ['PREPARANDO','CANCELADO'],
            'PREPARANDO' => ['PROCESADO','CANCELADO'],
            'PROCESADO'  => ['EN_RUTA','CANCELADO'],
            'EN_RUTA'    => ['ENTREGADO','NO_ENTREGADO'],
            'NO_ENTREGADO'=>[], 'ENTREGADO'=>[], 'CANCELADO'=>[],
        ];

        $current = $model->status;
        if (!isset($allowed[$current]) || !in_array($toStatus, $allowed[$current])) {
            throw new \RuntimeException("Transición inválida: {$current} → {$toStatus}");
        }

        $model->fill(array_merge(['status' => $toStatus], $extra));
        $model->save();

        return $model;
    }
}
