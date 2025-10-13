<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id','product_id','descripcion','cantidad','precio','descuento','impuesto','total'
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio'   => 'decimal:4',
        'descuento'=> 'decimal:2',
        'impuesto' => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    public function order(){ return $this->belongsTo(SalesOrder::class,'sales_order_id'); }
    public function product(){ return $this->belongsTo(Product::class); }
}
