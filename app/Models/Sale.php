<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
   protected $fillable = [
    'fecha','pos_register_id','warehouse_id','client_id','payment_type_id',
    'tipo_venta','subtotal','impuestos','descuento','total','status',
    'driver_id','user_id',
    // nuevos:
    'folio','moneda','price_list_id','credit_days',
    'delivery_type','entrega_nombre','entrega_telefono','entrega_calle','entrega_numero',
    'entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp',
    'shipping_route_id',
    'cfdi_uuid','cfdi_xml_path','cfdi_pdf_path','stamped_at','canceled_at',
];

protected $casts = [
    'fecha'       => 'datetime',
    'stamped_at'  => 'datetime',
    'canceled_at' => 'datetime',
    'subtotal'    => 'decimal:2',
    'impuestos'   => 'decimal:2',
    'descuento'   => 'decimal:2',
    'total'       => 'decimal:2',
];

public function items()           { return $this->hasMany(\App\Models\SaleItem::class); }
public function client()          { return $this->belongsTo(\App\Models\Client::class); }
public function warehouse()       { return $this->belongsTo(\App\Models\Warehouse::class); }
public function driver()          { return $this->belongsTo(\App\Models\Driver::class); }
public function route()           { return $this->belongsTo(\App\Models\ShippingRoute::class, 'shipping_route_id'); }
public function priceList()       { return $this->belongsTo(\App\Models\PriceList::class, 'price_list_id'); }
public function posRegister()     { return $this->belongsTo(\App\Models\PosRegister::class); }
public function paymentType()     { return $this->belongsTo(\App\Models\PaymentType::class, 'payment_type_id'); }

}
