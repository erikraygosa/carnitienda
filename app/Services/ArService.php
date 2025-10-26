<?php
// app/Services/ArService.php
namespace App\Services;

use App\Models\ArMovement;         // ← libreta de movimientos
use App\Models\ArPayment;          // ← pagos (ar_payments)
use App\Models\AccountsReceivable; // ← encabezado de documentos (opcional si lo usas)
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ArService
{
    /**
     * CARGO al cliente (ej. emisión de factura/nota).
     * Guarda movimiento en ar_movements.
     */
    public function charge(
        int $clientId,
        float $monto,
        ?string $desc = null,
        mixed $source = null,         // ej. modelo Factura
        ?string $fecha = null
    ): ArMovement {
        return DB::transaction(function () use ($clientId, $monto, $desc, $source, $fecha) {
            $mov = ArMovement::create([
                'client_id'   => $clientId,
                'fecha'       => $fecha ?: now()->toDateString(),
                'tipo'        => 'CARGO',
                'monto'       => $monto,
                'descripcion' => $desc,
                'created_by'  => Auth::id(),
            ]);

            if ($source) {
                $mov->source()->associate($source);
                $mov->save();
            }

            return $mov;
        });
    }

    /**
     * ABONO (Cobro) al cliente:
     * 1) Registra ABONO en ar_movements
     * 2) Crea pago en ar_payments enlazado al movimiento
     */
    public function payment(
        int $clientId,
        float $amount,
        int $paymentTypeId,
        ?string $reference = null,    // mapea a 'referencia'
        ?string $notes = null,        // mapea a 'nota'
        ?string $fecha = null,
        ?int $driverId = null         // si cobró el chofer
    ): ArPayment {
        return DB::transaction(function () use ($clientId,$amount,$paymentTypeId,$reference,$notes,$fecha,$driverId) {

            // 1) Movimiento ABONO
            $mov = ArMovement::create([
                'client_id'   => $clientId,
                'fecha'       => $fecha ?: now()->toDateString(),
                'tipo'        => 'ABONO',
                'monto'       => $amount,
                'descripcion' => 'Cobro',
                'created_by'  => Auth::id(),
            ]);

            // 2) Pago en ar_payments (usa columnas reales)
            $payment = ArPayment::create([
                'accounts_receivable_id' => null,        // usa esto solo si enlazas a un documento específico
                'fecha'                  => $mov->fecha,
                'payment_type_id'        => $paymentTypeId,
                'monto'                  => $amount,     // columna real en tu tabla
                'referencia'             => $reference,
                'driver_id'              => $driverId,
                'recibido_por'           => Auth::id(),
                'nota'                   => $notes,
            ]);

            // 3) Back-link del movimiento al pago (polimórfico)
            $mov->source_type = ArPayment::class;
            $mov->source_id   = $payment->id;
            $mov->save();

            return $payment;
        });
    }

    /**
     * Saldo actual del cliente = CARGOS - ABONOS
     * Calculado desde ar_movements.
     */
    public function saldoCliente(int $clientId): float
    {
        $rows = ArMovement::where('client_id', $clientId)
            ->selectRaw("
                SUM(CASE WHEN tipo='CARGO' THEN monto ELSE 0 END) AS cargos,
                SUM(CASE WHEN tipo='ABONO' THEN monto ELSE 0 END) AS abonos
            ")
            ->first();

        return (float) (($rows->cargos ?? 0) - ($rows->abonos ?? 0));
    }
}
