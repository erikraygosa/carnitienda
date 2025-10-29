<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSubproductRule extends Model
{
    protected $table = 'product_subproduct_rules';

    protected $fillable = [
        'main_product_id',
        'sub_product_id',
        'ratio',
        'merma_porcent',
        'activo',
    ];

    protected $casts = [
        'ratio'         => 'decimal:6', // string -> castea a float al usarlo
        'merma_porcent' => 'decimal:4',
        'activo'        => 'boolean',
    ];

    // Alias semánticos
    public function parent()     { return $this->belongsTo(Product::class, 'main_product_id'); }
    public function subproduct() { return $this->belongsTo(Product::class, 'sub_product_id'); }

    // Compat
    public function main() { return $this->belongsTo(Product::class, 'main_product_id'); }
    public function sub()  { return $this->belongsTo(Product::class, 'sub_product_id'); }
}
