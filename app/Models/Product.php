<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku','nombre','unidad','es_compuesto','es_subproducto',
        'precio_base','stock_min','activo','category_id',
        'barcode','tasa_iva','costo_promedio','maneja_inventario','notas',
    ];

    protected $casts = [
        'es_compuesto'      => 'boolean',
        'es_subproducto'    => 'boolean',
        'activo'            => 'boolean',
        'maneja_inventario' => 'boolean',
        'precio_base'       => 'decimal:4',
        'costo_promedio'    => 'decimal:4',
        'tasa_iva'          => 'decimal:2',
        'stock_min'         => 'decimal:3',
    ];

    /* ================== Relaciones principales ================== */

    // BOM (receta) -> ESTE producto se fabrica con estos componentes
    // Tabla: product_boms (product_id, component_product_id, cantidad, activo, ...)
    public function bomItems()
    {
        return $this->hasMany(ProductBom::class, 'product_id');
    }

    // En qué BOMs aparezco como componente
    public function usedInBoms()
    {
        return $this->hasMany(ProductBom::class, 'component_product_id');
    }

    // Reglas de subproducto donde ESTE es el PADRE (de aquí salen otros)
    // Tabla: product_subproduct_rules (parent_product_id, subproduct_id, rendimiento_pct, ...)
   public function subproductRulesAsParent()
{
    // Tabla: product_subproduct_rules (main_product_id, sub_product_id, ...)
    return $this->hasMany(ProductSubproductRule::class, 'main_product_id');
}

    // Reglas donde ESTE es el SUBPRODUCTO (hijo)
    public function parentRulesAsChild()
{
    return $this->hasMany(ProductSubproductRule::class, 'sub_product_id');
}

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function priceListItems()
    {
        return $this->hasMany(PriceListItem::class, 'product_id');
    }

    // Si usas estas tablas (las mencionaste en controladores/seeders)
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'product_id');
    }

    public function quoteItems()
    {
        return $this->hasMany(QuoteItem::class, 'product_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    /* ================== Scopes útiles ================== */

    public function scopeActive($q)
    {
        return $q->where('activo', true);
    }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $t = "%{$term}%";
        return $q->where(function ($qq) use ($t) {
            $qq->where('nombre', 'like', $t)
               ->orWhere('sku', 'like', $t)
               ->orWhere('barcode', 'like', $t);
        });
    }
}
