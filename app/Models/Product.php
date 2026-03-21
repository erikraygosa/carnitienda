<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        // ── Campos base ──────────────────────────────────────────────────────
        'sku', 'nombre', 'unidad', 'es_compuesto', 'es_subproducto',
        'precio_base', 'stock_min', 'activo', 'category_id',
        'barcode', 'tasa_iva', 'costo_promedio', 'maneja_inventario', 'notas',

        // ── Campos SAT / CFDI 4.0 ────────────────────────────────────────────
        'sat_clave_prod_serv',   // c_ClaveProdServ  — obligatorio
        'sat_clave_unidad',      // c_ClaveUnidad     — obligatorio (KGM, H87, LTR…)
        'sat_objeto_imp',        // c_ObjetoImp       — 01/02/03
        'sat_tipo_factor',       // c_TipoFactor      — Tasa | Exento | Cuota
        'sat_tasa_iva',          // Tasa IVA decimal  — 0.160000, 0.080000, 0.000000
        'sat_tasa_ieps',         // Tasa IEPS decimal — null si no aplica
        'sat_no_identificacion', // NoIdentificacion  — null = usa SKU o ID
    ];

    protected $casts = [
        'es_compuesto'        => 'boolean',
        'es_subproducto'      => 'boolean',
        'activo'              => 'boolean',
        'maneja_inventario'   => 'boolean',
        'precio_base'         => 'decimal:4',
        'costo_promedio'      => 'decimal:4',
        'tasa_iva'            => 'decimal:2',
        'stock_min'           => 'decimal:3',
        'sat_tasa_iva'        => 'decimal:6',
        'sat_tasa_ieps'       => 'decimal:6',
    ];

    // ── Accessors / Helpers SAT ───────────────────────────────────────────────

    /**
     * NoIdentificacion para el CFDI.
     * Prioridad: campo explícito → SKU → ID del producto.
     */
    public function getSatNoIdentificacionCfdiAttribute(): string
    {
        return $this->sat_no_identificacion
            ?? $this->sku
            ?? (string) $this->id;
    }

    /**
     * Devuelve true si el producto lleva IVA trasladado (Tasa).
     */
    public function tieneIva(): bool
    {
        return $this->sat_tipo_factor === 'Tasa'
            && ($this->sat_tasa_iva ?? 0) > 0;
    }

    /**
     * Devuelve true si el producto está exento de IVA.
     */
    public function esExentoIva(): bool
    {
        return $this->sat_tipo_factor === 'Exento';
    }

    /**
     * Devuelve true si el producto lleva IEPS.
     */
    public function tieneIeps(): bool
    {
        return ($this->sat_tasa_ieps ?? 0) > 0;
    }

    /**
     * Sincroniza sat_tasa_iva a partir de tasa_iva (porcentaje → decimal).
     * Llamar después de cambiar tasa_iva si no se gestiona manualmente.
     * Ej: tasa_iva = 16 → sat_tasa_iva = 0.160000
     */
    public function syncSatTasaIva(): void
    {
        if ($this->sat_tipo_factor === 'Exento') {
            $this->sat_tasa_iva = null;
        } else {
            $this->sat_tasa_iva = round((float) $this->tasa_iva / 100, 6);
        }
    }

    /**
     * Devuelve el array de impuestos trasladados listo para armar el CFDI.
     * Retorna array vacío si es "01" (no objeto de impuesto).
     */
    public function cfdiImpuestosTrasladados(): array
    {
        if ($this->sat_objeto_imp === '01') {
            return [];
        }

        $impuestos = [];

        // IVA
        if ($this->sat_tipo_factor === 'Exento') {
            $impuestos[] = [
                'Base'       => null, // se calcula por concepto
                'Impuesto'   => '002',
                'TipoFactor' => 'Exento',
                'TasaOCuota' => null,
                'Importe'    => null,
            ];
        } elseif ($this->tieneIva()) {
            $impuestos[] = [
                'Base'       => null,
                'Impuesto'   => '002',
                'TipoFactor' => 'Tasa',
                'TasaOCuota' => number_format((float) $this->sat_tasa_iva, 6, '.', ''),
                'Importe'    => null,
            ];
        }

        // IEPS (si aplica)
        if ($this->tieneIeps()) {
            $impuestos[] = [
                'Base'       => null,
                'Impuesto'   => '003',
                'TipoFactor' => 'Tasa',
                'TasaOCuota' => number_format((float) $this->sat_tasa_ieps, 6, '.', ''),
                'Importe'    => null,
            ];
        }

        return $impuestos;
    }

    // ── Relaciones BOM ────────────────────────────────────────────────────────

    public function bomItems()
    {
        return $this->hasMany(ProductBom::class, 'parent_product_id', 'id');
    }

    public function usedInBoms()
    {
        return $this->hasMany(ProductBom::class, 'component_product_id', 'id');
    }

    public function components()
    {
        return $this->belongsToMany(
            Product::class,
            'product_boms',
            'parent_product_id',
            'component_product_id'
        )->withPivot(['cantidad', 'activo']);
    }

    public function parents()
    {
        return $this->belongsToMany(
            Product::class,
            'product_boms',
            'component_product_id',
            'parent_product_id'
        )->withPivot(['cantidad', 'activo']);
    }

    // ── Subproductos ──────────────────────────────────────────────────────────

    public function subproductRulesAsParent()
    {
        return $this->hasMany(ProductSubproductRule::class, 'main_product_id', 'id');
    }

    public function parentRulesAsChild()
    {
        return $this->hasMany(ProductSubproductRule::class, 'sub_product_id', 'id');
    }

    // ── Otras relaciones ──────────────────────────────────────────────────────

    public function category()       { return $this->belongsTo(Category::class); }
    public function priceListItems() { return $this->hasMany(PriceListItem::class, 'product_id'); }
    public function saleItems()      { return $this->hasMany(SaleItem::class, 'product_id'); }
    public function quoteItems()     { return $this->hasMany(QuoteItem::class, 'product_id'); }
    public function stockMovements() { return $this->hasMany(StockMovement::class, 'product_id'); }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($q)  { return $q->where('activo', true); }

    public function scopeSearch($q, ?string $term)
    {
        if (!$term) return $q;
        $t = "%{$term}%";
        return $q->where(function ($qq) use ($t) {
            $qq->where('nombre',  'like', $t)
               ->orWhere('sku',     'like', $t)
               ->orWhere('barcode', 'like', $t);
        });
    }

    // ── Helpers generales ─────────────────────────────────────────────────────

    public function isComposite(): bool  { return (bool) $this->es_compuesto; }
    public function managesStock(): bool { return (bool) $this->maneja_inventario; }
}