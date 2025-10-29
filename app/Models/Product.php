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

    /* ====== Relaciones BOM ====== */

    // ESTE producto (padre) se arma con estos componentes
    public function bomItems()
    {
        // FK correcta: parent_product_id
        return $this->hasMany(ProductBom::class, 'parent_product_id', 'id');
    }

    // En qué BOMs aparezco como componente
    public function usedInBoms()
    {
        return $this->hasMany(ProductBom::class, 'component_product_id', 'id');
    }

    // Atajo: componentes (productos) del compuesto
    public function components()
    {
        return $this->belongsToMany(
            Product::class,
            'product_boms',
            'parent_product_id',     // FK a este (padre)
            'component_product_id'   // FK al producto componente
        )->withPivot(['cantidad','activo']);
    }

    // Atajo: padres en los que este producto participa como componente
    public function parents()
    {
        return $this->belongsToMany(
            Product::class,
            'product_boms',
            'component_product_id',  // este producto en la pivot
            'parent_product_id'      // productos padre
        )->withPivot(['cantidad','activo']);
    }

    /* ====== Subproductos ====== */

    public function subproductRulesAsParent()
    {
        return $this->hasMany(ProductSubproductRule::class, 'main_product_id', 'id');
    }

    public function parentRulesAsChild()
    {
        return $this->hasMany(ProductSubproductRule::class, 'sub_product_id', 'id');
    }

    /* ====== Otras ====== */
    public function category()       { return $this->belongsTo(Category::class); }
    public function priceListItems() { return $this->hasMany(PriceListItem::class, 'product_id'); }
    public function saleItems()      { return $this->hasMany(SaleItem::class, 'product_id'); }
    public function quoteItems()     { return $this->hasMany(QuoteItem::class, 'product_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'product_id'); }

    /* ====== Scopes ====== */
    public function scopeActive($q)  { return $q->where('activo', true); }

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

    /* ====== Helpers ====== */

    public function isComposite(): bool
    {
        return (bool) $this->es_compuesto;
    }

    public function managesStock(): bool
    {
        return (bool) $this->maneja_inventario;
    }
}
