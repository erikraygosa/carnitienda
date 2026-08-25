<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;

class Invoice extends Model
{
     use BelongsToCompany;
    protected $fillable = [
        'client_id','sales_order_id','sale_id','ar_payment_id','related_invoice_id',
        'serie','folio','fecha','tipo_comprobante',
        'lugar_expedicion','exportacion',
        'regimen_fiscal_emisor','regimen_fiscal_receptor',
        'receptor_rfc','receptor_razon_social','receptor_cp',
        'forma_pago','metodo_pago','uso_cfdi','condiciones_pago','cuenta',
        'moneda','subtotal','impuestos','total',
        'uuid','factuapi_id','estatus','version_cfdi','xml_timbrado',
        'sello_cfdi','sello_sat','numero_certificado_sat','rfc_provider_cert',
        'created_by','owner_id',
    ];

    protected $casts = [
        'fecha'     => 'datetime',
        'subtotal'  => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total'     => 'decimal:2',
    ];

    // Relaciones
    public function items()          { return $this->hasMany(InvoiceItem::class); }
    public function client()         { return $this->belongsTo(Client::class); }
    public function salesOrder()     { return $this->belongsTo(SalesOrder::class); }
    public function sale()           { return $this->belongsTo(Sale::class); }
    public function arPayment()      { return $this->belongsTo(ArPayment::class); }
    public function complementDocs() { return $this->hasMany(InvoiceComplementDoc::class); }

    /** Factura de Ingreso original que afecta esta Nota de Crédito (tipo E) */
    public function relatedInvoiceOriginal() { return $this->belongsTo(Invoice::class, 'related_invoice_id'); }
    /** Notas de crédito emitidas contra esta factura */
    public function notasCredito() { return $this->hasMany(Invoice::class, 'related_invoice_id'); }

    /** Cobros aplicados directamente a esta factura (facturas libres, sin pedido) */
    public function arPaymentItems()
    {
        return $this->hasMany(ArPaymentItem::class, 'invoice_id');
    }

    public function esLibre(): bool
    {
        return empty($this->sales_order_id) && empty($this->sale_id);
    }

    /** Saldo pendiente de cobro de una factura libre (total - cobros ya aplicados) */
    public function saldoPendiente(): float
    {
        $cobrado = $this->arPaymentItems()->sum('monto_aplicado');
        return round((float) $this->total - (float) $cobrado, 2);
    }

    // Helpers de estado
    public function isDraft()     { return $this->estatus === 'BORRADOR'; }
    public function isStamped()   { return $this->estatus === 'TIMBRADA'; }
    public function isCanceled()  { return $this->estatus === 'CANCELADA'; }
    public function isCancellationPending() { return $this->estatus === 'CANCELACION_PENDIENTE'; }
}
