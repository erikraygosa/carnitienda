<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBom extends Model
{
    protected $table = 'product_boms';

    protected $fillable = [
        'parent_product_id',
        'component_product_id',
        'cantidad',
        'activo',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3', // Ojo: Eloquent devuelve string, castea a float al operar
        'activo'   => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function component()
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    /* Opcional: scope activos */
    public function scopeActive($q)
    {
        return $q->where('activo', true);
    }
}
