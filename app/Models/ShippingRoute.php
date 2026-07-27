<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingRoute extends Model
{
    protected $fillable = ['nombre','descripcion','activo'];
    protected $casts = ['activo' => 'boolean'];

    public function clients()
    {
        return $this->hasMany(\App\Models\Client::class, 'shipping_route_id');
    }
}
