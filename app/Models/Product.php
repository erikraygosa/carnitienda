<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku','nombre','unidad','es_compuesto','es_subproducto','precio_base','stock_min','activo'
    ];

    protected $casts = [
        'es_compuesto' => 'boolean',
        'es_subproducto' => 'boolean',
        'precio_base' => 'decimal:4',
        'stock_min' => 'decimal:3',
        'activo' => 'boolean',
    ];

    public function bomComponents()
    {
        return $this->hasMany(ProductBom::class, 'parent_product_id');
    }

    public function usedInBoms()
    {
        return $this->hasMany(ProductBom::class, 'component_product_id');
    }

    public function subproductRules()
    {
        return $this->hasMany(ProductSubproductRule::class, 'sub_product_id');
    }

    public function mainProductRules()
    {
        return $this->hasMany(ProductSubproductRule::class, 'main_product_id');
    }
    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }
}
