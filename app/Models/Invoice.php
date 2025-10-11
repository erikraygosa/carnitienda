<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'client_id','serie','folio','fecha','forma_pago','metodo_pago','uso_cfdi','moneda',
        'subtotal','impuestos','total','uuid','estatus','version_cfdi','xml_timbrado',
        'created_by','owner_id'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total' => 'decimal:2',
        'xml_timbrado' => 'array',
    ];

    public function items() { return $this->hasMany(InvoiceItem::class); }
}
