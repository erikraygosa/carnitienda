<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchItem extends Model
{
    protected $fillable = [
        'dispatch_id',
        'sales_order_id',
        'referencia',
        'volumen',
        'peso',
        'status',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

            public function lines(): HasMany
        {
            return $this->hasMany(DispatchItemLine::class);
        }
    
}