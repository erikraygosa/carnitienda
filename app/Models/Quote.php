<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToCompany;

class Quote extends Model
{   
    use BelongsToCompany;
    protected $fillable = [
        'fecha',
        'client_id',
        'price_list_id',
        'moneda',
        'subtotal',
        'impuestos',
        'descuento',
        'total',
        'vigencia_hasta',
        'status',
        'created_by',
        'owner_id',
        'shipping_route_id',
        'payment_method', 'credit_days', 'delivery_type',
        'entrega_nombre', 'entrega_telefono', 'entrega_calle',
        'entrega_numero', 'entrega_colonia', 'entrega_ciudad',
        'entrega_estado', 'entrega_cp',
    ];

    protected $casts = [
        'fecha'          => 'datetime',
        'vigencia_hasta' => 'date',
        'subtotal'       => 'decimal:2',
        'impuestos'      => 'decimal:2',
        'descuento'      => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    // Relaciones
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }

    public function items(): HasMany { return $this->hasMany(QuoteItem::class); }

    // Helper para etiqueta en español
    public function getStatusLabelAttribute(): string
    {
        $map = [
            'BORRADOR'   => 'Borrador',
            'ENVIADA'    => 'Enviada',
            'APROBADA'   => 'Aprobada',
            'RECHAZADA'  => 'Rechazada',
            'CONVERTIDA' => 'Convertida',
            'CANCELADA'  => 'Cancelada',
        ];
        return $map[$this->status] ?? $this->status;
    }
}
