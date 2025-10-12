<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'nombre','email','telefono','direccion','activo',
        'tipo_persona','rfc','razon_social','nombre_comercial','regimen_fiscal','uso_cfdi_default',
        'shipping_route_id','payment_type_id','price_list_id',
        'credito_limite','credito_dias'
    ];

   protected $casts = [
    'activo' => 'boolean',
    'credito_limite' => 'decimal:2',
    'credito_dias' => 'integer',
];

    public function shippingRoute() { return $this->belongsTo(ShippingRoute::class); }
    public function paymentType()  { return $this->belongsTo(PaymentType::class); }
    public function priceList()    { return $this->belongsTo(PriceList::class); }

    public function priceOverrides()
    {
        return $this->hasMany(ClientPriceOverride::class);
    }
}
