<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id',
        'product_id',
        'descripcion',
        'cantidad',
        'precio',
        'descuento',
        'impuesto', // importe (no porcentaje)
        'total',
    ];

    protected $casts = [
        'cantidad'  => 'decimal:3',
        'precio'    => 'decimal:4',
        'descuento' => 'decimal:2',
        'impuesto'  => 'decimal:2',
        'total'     => 'decimal:2',
    ];

    public function quote(): BelongsTo { return $this->belongsTo(Quote::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
