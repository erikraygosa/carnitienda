<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'nombre','email','telefono','direccion','activo',
        'tipo_persona','rfc','razon_social','nombre_comercial','regimen_fiscal','uso_cfdi_default',
        'shipping_route_id','payment_type_id','price_list_id',
        'credito_limite','credito_dias',
        // Dirección fiscal
        'fiscal_calle','fiscal_numero','fiscal_colonia',
        'fiscal_ciudad','fiscal_estado','fiscal_cp',
        // Dirección de entrega
        'entrega_calle','entrega_numero','entrega_colonia',
        'entrega_ciudad','entrega_estado','entrega_cp',
        'entrega_igual_fiscal',
    ];

    protected $casts = [
        'activo'               => 'boolean',
        'credito_limite'       => 'decimal:2',
        'credito_dias'         => 'integer',
        'entrega_igual_fiscal' => 'boolean',
    ];

    public function shippingRoute() { return $this->belongsTo(ShippingRoute::class); }
    public function paymentType()   { return $this->belongsTo(PaymentType::class); }
    public function priceList()     { return $this->belongsTo(PriceList::class); }
    public function priceOverrides(){ return $this->hasMany(ClientPriceOverride::class); }

    /**
     * Devuelve la dirección de entrega efectiva:
     * si entrega_igual_fiscal, usa la fiscal; si no, la de entrega.
     */
    public function getEntregaEfectiva(): array
    {
        if ($this->entrega_igual_fiscal) {
            return [
                'calle'   => $this->fiscal_calle,
                'numero'  => $this->fiscal_numero,
                'colonia' => $this->fiscal_colonia,
                'ciudad'  => $this->fiscal_ciudad,
                'estado'  => $this->fiscal_estado,
                'cp'      => $this->fiscal_cp,
            ];
        }
        return [
            'calle'   => $this->entrega_calle,
            'numero'  => $this->entrega_numero,
            'colonia' => $this->entrega_colonia,
            'ciudad'  => $this->entrega_ciudad,
            'estado'  => $this->entrega_estado,
            'cp'      => $this->entrega_cp,
        ];
    }
}