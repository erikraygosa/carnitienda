<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\AccountsReceivable;
use App\Models\ArPayment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountsReceivableService
{
    public function createFromSale(Sale $sale, ?int $diasCredito = null, ?int $userId = null): AccountsReceivable
    {
        return DB::transaction(function () use ($sale, $diasCredito, $userId) {
            $venc = $diasCredito ? Carbon::parse($sale->fecha)->addDays($diasCredito) : null;
            $ar = AccountsReceivable::create([
                'client_id' => $sale->client_id,
                'tipo_doc' => 'FA',
                'folio_documento' => 'S-' . $sale->id,
                'fecha' => Carbon::parse($sale->fecha)->toDateString(),
                'vencimiento' => $venc?->toDateString(),
                'moneda' => 'MXN',
                'subtotal' => $sale->subtotal,
                'impuestos' => $sale->impuestos,
                'total' => $sale->total,
                'saldo' => $sale->total,
                'status' => 'ABIERTA',
                'warehouse_id' => $sale->warehouse_id,
                'driver_id' => $sale->driver_id,
                'created_by' => $userId,
                'sale_id' => $sale->id,
            ]);
            return $ar;
        });
    }

    public function registerPayment(AccountsReceivable $ar, float $monto, string $fecha, int $paymentTypeId, ?string $ref = null, ?int $userId = null): ArPayment
    {
        return DB::transaction(function () use ($ar, $monto, $fecha, $paymentTypeId, $ref, $userId) {
            $pay = ArPayment::create([
                'accounts_receivable_id' => $ar->id,
                'fecha' => $fecha,
                'payment_type_id' => $paymentTypeId,
                'monto' => $monto,
                'referencia' => $ref,
                'recibido_por' => $userId,
            ]);
            $nuevoSaldo = max(0, (float)$ar->saldo - $monto);
            $ar->update([
                'saldo' => $nuevoSaldo,
                'status' => $nuevoSaldo <= 0 ? 'PAGADA' : 'PARCIAL',
            ]);
            return $pay;
        });
    }
}
