<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArPaymentItem extends Model
{
    protected $fillable = [
        'ar_payment_id',
        'sales_order_id',
        'sale_id',
        'invoice_id',
        'monto_aplicado',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(ArPayment::class, 'ar_payment_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** Nota de venta (mostrador) cubierta directamente, cuando aplica a una Sale y no a un pedido. */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Factura libre cubierta directamente (cuando no hay sales_order_id).
     */
    public function invoiceLibre()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * La factura PPD relacionada a este cobro, venga de un pedido o sea
     * una factura libre cubierta directamente.
     */
    public function relatedInvoice(): ?Invoice
    {
        return $this->invoice_id
            ? $this->invoiceLibre
            : $this->salesOrder?->invoice;
    }

    /** Cliente de la nota (pedido o venta de mostrador) o de la factura libre, según cuál aplique. */
    public function relatedClient(): ?Client
    {
        if ($this->invoice_id) {
            return $this->invoiceLibre?->client;
        }

        return $this->sale_id ? $this->sale?->client : $this->salesOrder?->client;
    }
}