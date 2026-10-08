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
        'uuid','factuapi_id','estatus','version_cfdi','xml_timbrado','fecha_timbrado',
        'sello_cfdi','sello_sat','numero_certificado_sat','rfc_provider_cert',
        'created_by','owner_id',
    ];

    protected $casts = [
        'fecha'     => 'datetime',
        'fecha_timbrado' => 'datetime',
        'subtotal'  => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total'     => 'decimal:2',
    ];

    // Relaciones
    public function items()          { return $this->hasMany(InvoiceItem::class); }
    public function client()         { return $this->belongsTo(Client::class); }
    public function salesOrder()     { return $this->belongsTo(SalesOrder::class); }
    /** Todos los pedidos que cubre esta factura (uno solo, o varios si es consolidada). */
    public function salesOrders()    { return $this->belongsToMany(SalesOrder::class, 'invoice_sales_orders'); }
    public function sale()           { return $this->belongsTo(Sale::class); }
    /** Todas las notas de venta que cubre esta factura (una sola, o varias si es consolidada). */
    public function sales()          { return $this->belongsToMany(Sale::class, 'invoice_sales'); }
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

    /**
     * Filtros del listado de facturas (los usa la pantalla y el Excel, para que
     * coincidan). Claves: search, tipo, estatus, forma_pago, metodo_pago,
     * campo_fecha (fecha | fecha_timbrado), desde, hasta.
     */
    public function scopeFiltrarListado($q, array $f)
    {
        $campoFecha = ($f['campo_fecha'] ?? 'fecha') === 'fecha_timbrado' ? 'fecha_timbrado' : 'fecha';

        return $q
            ->when(! empty($f['search']), function ($q) use ($f) {
                $t = '%' . $f['search'] . '%';
                $q->where(fn ($q) => $q->where('folio', 'like', $t)
                    ->orWhere('serie', 'like', $t)
                    ->orWhere('uuid', 'like', $t)
                    ->orWhereHas('client', fn ($q) => $q->where('nombre', 'like', $t)->orWhere('rfc', 'like', $t)));
            })
            ->when(! empty($f['tipo']),        fn ($q) => $q->where('tipo_comprobante', $f['tipo']))
            ->when(! empty($f['estatus']),     fn ($q) => $q->where('estatus', $f['estatus']))
            ->when(! empty($f['forma_pago']),  fn ($q) => $q->where('forma_pago', $f['forma_pago']))
            ->when(! empty($f['metodo_pago']), fn ($q) => $q->where('metodo_pago', $f['metodo_pago']))
            ->when(! empty($f['desde']),       fn ($q) => $q->whereDate($campoFecha, '>=', $f['desde']))
            ->when(! empty($f['hasta']),       fn ($q) => $q->whereDate($campoFecha, '<=', $f['hasta']));
    }

    /** Importe que cuenta para sumas: facturas vigentes suman, notas de crédito restan; canceladas, borradores y complementos no. */
    public function getImporteParaSumaAttribute(): float
    {
        if (! in_array($this->estatus, ['TIMBRADA', 'CANCELACION_PENDIENTE'], true)) {
            return 0.0;
        }

        return match ($this->tipo_comprobante) {
            'I' => (float) $this->total,
            'E' => -(float) $this->total,
            default => 0.0,
        };
    }
}
