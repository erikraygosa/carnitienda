<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispatch extends Model
{
    protected $fillable = [
        'folio','warehouse_id','shipping_route_id','driver_id','vehicle',
        'fecha','status','notas',
    ];

    protected $casts = ['fecha' => 'datetime'];

    public function items(): HasMany {
        return $this->hasMany(DispatchItem::class);
    }

    public function route()   { return $this->belongsTo(ShippingRoute::class, 'shipping_route_id'); }
    public function warehouse(){ return $this->belongsTo(Warehouse::class); }
    public function driver()  { return $this->belongsTo(Driver::class); }
}
