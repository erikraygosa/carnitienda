<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispatch extends Model
{
    protected $fillable = [
        'folio',
        'warehouse_id',
        'shipping_route_id',
        'driver_id',
        'vehicle',
        'fecha',
        'status',
        'notas',
        'en_ruta_at',
        'cerrado_at',
        'monto_liquidado',
        'notas_cierre',
    ];

    protected $casts = [
        'fecha'      => 'datetime',
        'en_ruta_at' => 'datetime',
        'cerrado_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DispatchItem::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(ShippingRoute::class, 'shipping_route_id');
    }
        public function arAssignments()
    {
        return $this->hasMany(DispatchArAssignment::class);
    }
    public function transferAssignments()
    {
        return $this->hasMany(DispatchTransferAssignment::class);
    }
}