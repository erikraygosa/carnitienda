<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCut extends Model
{
    protected $fillable = [
        'company_id', 'folio', 'periodo_inicio', 'periodo_fin', 'cortesia',
        'timbres_usados', 'cancelados', 'excedente', 'precio_timbre', 'mensualidad',
        'importe_excedente', 'total', 'estado', 'fecha_pago', 'notas', 'generado_por',
    ];

    protected $casts = [
        'periodo_inicio'    => 'date',
        'periodo_fin'       => 'date',
        'fecha_pago'        => 'date',
        'precio_timbre'     => 'decimal:2',
        'mensualidad'       => 'decimal:2',
        'importe_excedente' => 'decimal:2',
        'total'             => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
