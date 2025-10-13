<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id','product_id','qty_received','price','total',
        // si luego agregas descuento / iva aquí, añádelos al fillable
    ];

    protected $casts = [
        'qty_received' => 'float',
        'price'        => 'float',
        'total'        => 'float',
    ];

    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
