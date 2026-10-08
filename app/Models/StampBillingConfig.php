<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampBillingConfig extends Model
{
    protected $fillable = [
        'company_id', 'cortesia_mensual', 'precio_timbre', 'mensualidad',
        'dia_corte', 'inicio_cobro', 'activo', 'notas',
    ];

    protected $casts = [
        'precio_timbre' => 'decimal:2',
        'mensualidad'   => 'decimal:2',
        'inicio_cobro'  => 'date',
        'activo'        => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
