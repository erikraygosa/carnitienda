<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductWarehousePrice extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'precio', 'updated_by'];

    protected $casts = [
        'precio' => 'decimal:4',
    ];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function updatedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
