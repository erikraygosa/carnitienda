<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'provider_id','warehouse_id','user_id','folio','fecha','expected_at',
        'status','subtotal','discount_total','tax_total','total','currency','observaciones','payment_method',
    ];

    protected $casts = [
        'fecha'       => 'date',
        'expected_at' => 'date',
    ];

    /** Para exponer los accessors en arrays/json y usarlos fácil en vistas */
    protected $appends = ['status_label', 'status_badge_class'];

    // Relaciones
    public function provider(): BelongsTo { return $this->belongsTo(Provider::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(PurchaseOrderItem::class); }

    // ---- Accessors de estado (ES) ----
    public function getStatusLabelAttribute(): string
    {
        return [
            'draft'              => 'Borrador',
            'approved'           => 'Aprobada',
            'partially_received' => 'Parcialmente recibida',
            'closed'             => 'Cerrada',
            'cancelled'          => 'Cancelada',
        ][$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return [
            'draft'              => 'bg-gray-100 text-gray-700',
            'approved'           => 'bg-blue-100 text-blue-700',
            'partially_received' => 'bg-amber-100 text-amber-700',
            'closed'             => 'bg-emerald-100 text-emerald-700',
            'cancelled'          => 'bg-rose-100 text-rose-700',
        ][$this->status] ?? 'bg-slate-100 text-slate-700';
    }
}
