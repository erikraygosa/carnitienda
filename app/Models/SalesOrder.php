<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $fillable = [
        'client_id','warehouse_id','price_list_id','folio','fecha','programado_para',
        'delivery_type','entrega_nombre','entrega_telefono','entrega_calle','entrega_numero',
        'entrega_colonia','entrega_ciudad','entrega_estado','entrega_cp',
        'shipping_route_id','driver_id','payment_method','credit_days',
        'moneda','subtotal','impuestos','descuento','total','status',
        'created_by','owner_id',
    ];

    protected $casts = [
        'fecha'          => 'datetime',
        'programado_para'=> 'date',
    ];

    // Relaciones
    public function client(){ return $this->belongsTo(Client::class); }
    public function warehouse(){ return $this->belongsTo(Warehouse::class); }
    public function priceList(){ return $this->belongsTo(PriceList::class); }
    public function driver(){ return $this->belongsTo(Driver::class); }
    public function route(){ return $this->belongsTo(ShippingRoute::class,'shipping_route_id'); }

    public function items(): HasMany { return $this->hasMany(SalesOrderItem::class); }

    // Etiquetas de estado
    public function getStatusLabelAttribute(): string
    {
        return [
            'BORRADOR'   => 'Borrador',
            'APROBADO'   => 'Aprobado',
            'PROCESADO'  => 'Procesado',
            'DESPACHADO' => 'Despachado',
            'ENTREGADO'  => 'Entregado',
            'CANCELADO'  => 'Cancelado',
        ][$this->status] ?? $this->status;
    }
}
