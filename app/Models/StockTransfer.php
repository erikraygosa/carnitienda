<?php
// app/Models/StockTransfer.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StockTransfer extends Model
{
    protected $fillable = [
        'folio', 'from_warehouse_id', 'to_warehouse_id',
        'fecha', 'status', 'dispatch_id', 'notas',
        'created_by', 'completado_at',
    ];

    protected $casts = [
        'fecha'         => 'date',
        'completado_at' => 'datetime',
    ];

    // Statuses: PENDIENTE → ASIGNADO → EN_RUTA → COMPLETADO | CANCELADO

    public function fromWarehouse()  { return $this->belongsTo(Warehouse::class, 'from_warehouse_id'); }
    public function toWarehouse()    { return $this->belongsTo(Warehouse::class, 'to_warehouse_id'); }
    public function items()          { return $this->hasMany(StockTransferItem::class); }
    public function creator()        { return $this->belongsTo(User::class, 'created_by'); }
    public function dispatch()       { return $this->belongsTo(Dispatch::class); }
    public function dispatchAssignment() {
        return $this->hasOne(DispatchTransferAssignment::class);
    }

    public static function generateFolio(): string
    {
        $last = static::max('id') ?? 0;
        return 'TRF-' . now()->format('Ymd') . '-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'PENDIENTE'      => 'Pendiente',
            'ASIGNADO'       => 'Asignado a despacho',
            'EN_RUTA'        => 'En ruta',
            'COMPLETADO'     => 'Completado',
            'CANCELADO'      => 'Cancelado',
            default          => $this->status,
        };
    }
}