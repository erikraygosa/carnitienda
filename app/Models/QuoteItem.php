<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $fillable = ['quote_id','product_id','descripcion','cantidad','precio','descuento','impuesto','total'];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio' => 'decimal:4',
        'descuento' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function quote()   { return $this->belongsTo(Quote::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
