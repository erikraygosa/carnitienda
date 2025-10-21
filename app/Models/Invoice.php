<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'client_id','sales_order_id','sale_id',
        'serie','folio','fecha','tipo_comprobante',
        'lugar_expedicion','exportacion',
        'regimen_fiscal_emisor','regimen_fiscal_receptor',
        'receptor_rfc','receptor_razon_social','receptor_cp',
        'forma_pago','metodo_pago','uso_cfdi','condiciones_pago','cuenta',
        'moneda','subtotal','impuestos','total',
        'uuid','estatus','version_cfdi','xml_timbrado',
        'created_by','owner_id',
    ];

    protected $casts = [
        'fecha'     => 'datetime',
        'subtotal'  => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total'     => 'decimal:2',
    ];

    // Relaciones
    public function items()       { return $this->hasMany(InvoiceItem::class); }
    public function client()      { return $this->belongsTo(Client::class); }
    public function salesOrder()  { return $this->belongsTo(SalesOrder::class); }
    public function sale()        { return $this->belongsTo(Sale::class); }

    // Helpers de estado
    public function isDraft()     { return $this->estatus === 'BORRADOR'; }
    public function isStamped()   { return $this->estatus === 'TIMBRADA'; }
    public function isCanceled()  { return $this->estatus === 'CANCELADA'; }
}
