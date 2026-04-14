<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchItemLine extends Model
{
    protected $fillable = [
        'dispatch_item_id',
        'sales_order_item_id',
        'qty_solicitada',
        'qty_despachada',
        'nota',
    ];

    protected $casts = [
        'qty_solicitada' => 'decimal:3',
        'qty_despachada' => 'decimal:3',
    ];

    public function dispatchItem(): BelongsTo
    {
        return $this->belongsTo(DispatchItem::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    // Diferencia: positivo = se despachó de más, negativo = faltante
    public function getDiferenciaAttribute(): ?float
    {
        if (is_null($this->qty_despachada)) return null;
        return round((float)$this->qty_despachada - (float)$this->qty_solicitada, 3);
    }
}