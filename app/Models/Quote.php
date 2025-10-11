<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    protected $fillable = [
        'fecha','client_id','price_list_id','moneda','subtotal','impuestos','descuento','total',
        'vigencia_hasta','status','created_by','owner_id'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'vigencia_hasta' => 'date',
        'subtotal' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function items() { return $this->hasMany(QuoteItem::class); }
    public function client() { return $this->belongsTo(Client::class); }
}
