<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBom extends Model
{
    protected $fillable = ['parent_product_id','component_product_id','cantidad','activo'];
    protected $casts = ['cantidad' => 'decimal:3', 'activo' => 'boolean'];

    public function parent()    { return $this->belongsTo(Product::class, 'parent_product_id'); }
    public function component() { return $this->belongsTo(Product::class, 'component_product_id'); }
}
