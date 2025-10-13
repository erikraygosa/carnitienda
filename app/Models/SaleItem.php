<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
protected $fillable = [
    'sale_id','product_id','descripcion','cantidad','precio','descuento','impuesto','total',
];

protected $casts = [
    'cantidad'  => 'decimal:3',
    'precio'    => 'decimal:4',
    'descuento' => 'decimal:2',
    'impuesto'  => 'decimal:2',
    'total'     => 'decimal:2',
];

public function sale()    { return $this->belongsTo(\App\Models\Sale::class); }
public function product() { return $this->belongsTo(\App\Models\Product::class); }

}
