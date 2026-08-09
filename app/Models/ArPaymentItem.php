<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArPaymentItem extends Model
{
    protected $fillable = [
        'ar_payment_id',
        'sales_order_id',
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

    /** Cliente de la nota o de la factura libre, según cuál aplique. */
    public function relatedClient(): ?Client
    {
        return $this->invoice_id
            ? $this->invoiceLibre?->client
            : $this->salesOrder?->client;
    }
}