<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchArAssignment extends Model
{
    protected $fillable = [
        'dispatch_id',
        'client_id',
        'saldo_asignado',
        'monto_cobrado',
        'status',
    ];

    public function dispatch() { return $this->belongsTo(Dispatch::class); }
    public function client()   { return $this->belongsTo(Client::class); }

    // Notas/pedidos específicos que se marcaron para esta asignación — antes
    // no se guardaban y cualquier pantalla que necesitara "las notas de este
    // despacho" volvía a traer TODAS las notas pendientes del cliente,
    // ignorando cuáles se habían seleccionado realmente.
    public function orders()
    {
        return $this->belongsToMany(SalesOrder::class, 'dispatch_ar_assignment_orders', 'dispatch_ar_assignment_id', 'sales_order_id');
    }

    // Notas de venta (Sale) a crédito asignadas a esta cobranza.
    public function sales()
    {
        return $this->belongsToMany(Sale::class, 'dispatch_ar_assignment_sales', 'dispatch_ar_assignment_id', 'sale_id');
    }

    /** Saldo por cobrar de una nota (pedido o nota de venta). */
    public static function saldoDe($nota): float
    {
        return ($nota->saldo_pendiente !== null && (float) $nota->saldo_pendiente > 0)
            ? (float) $nota->saldo_pendiente
            : (float) $nota->total;
    }

    /** Pedidos + notas de venta de esta asignación. */
    public function totalNotas(): int
    {
        return $this->orders()->count() + $this->sales()->count();
    }

    /** Recalcula saldo_asignado sobre TODAS sus notas (pedidos + notas de venta). */
    public function recalcularSaldo(): void
    {
        $saldo = $this->orders()->get(['sales_orders.id', 'total', 'saldo_pendiente'])->sum(fn ($n) => self::saldoDe($n))
               + $this->sales()->get(['sales.id', 'total', 'saldo_pendiente'])->sum(fn ($n) => self::saldoDe($n));
        $this->update(['saldo_asignado' => round($saldo, 2)]);
    }

    /**
     * Pedidos + notas de venta de esta asignación en una sola lista
     * (id, folio, fecha, total, saldo_pendiente, tipo 'pedido'|'venta').
     */
    public function notasCombinadas(bool $soloPendientes = false)
    {
        $pendiente = fn ($n) => $n->saldo_pendiente === null || (float) $n->saldo_pendiente > 0;

        $os = $this->orders()->get(['sales_orders.id', 'sales_orders.folio', 'sales_orders.fecha', 'sales_orders.programado_para', 'sales_orders.entregado_at', 'sales_orders.total', 'sales_orders.saldo_pendiente'])
            ->each(fn ($n) => $n->tipo = 'pedido');
        $vs = $this->sales()->get(['sales.id', 'sales.folio', 'sales.fecha', 'sales.entregado_at', 'sales.total', 'sales.saldo_pendiente'])
            ->each(fn ($n) => $n->tipo = 'venta');

        $todas = $os->concat($vs);
        if ($soloPendientes) {
            $todas = $todas->filter($pendiente);
        }

        return $todas->values();
    }
}
