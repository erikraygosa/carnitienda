<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'fecha','pos_register_id','warehouse_id','client_id','payment_type_id','tipo_venta',
        'subtotal','impuestos','descuento','total','status','driver_id','user_id','created_by','owner_id'
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function items()         { return $this->hasMany(SaleItem::class); }
    public function client()        { return $this->belongsTo(Client::class); }
    public function warehouse()     { return $this->belongsTo(Warehouse::class); }
    public function driver()        { return $this->belongsTo(Driver::class); }
    public function paymentType()   { return $this->belongsTo(PaymentType::class); }
}
