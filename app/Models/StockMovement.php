<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'warehouse_id','product_id','tipo','cantidad','motivo','referencia_type','referencia_id','user_id'
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
    ];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function product()   { return $this->belongsTo(Product::class); }

    public function referencia()
    {
        return $this->morphTo();
    }
}
