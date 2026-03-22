<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class InvoiceSeries extends Model
{
    protected $fillable = [
        'company_id',
        'serie',
        'tipo_comprobante',
        'folio_actual',
        'folio_inicio',
        'activa',
        'es_default',
        'descripcion',
    ];

    protected $casts = [
        'activa'     => 'boolean',
        'es_default' => 'boolean',
    ];

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeParaTipo($query, string $tipo)
    {
        return $query->where('tipo_comprobante', $tipo);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Obtiene y reserva el siguiente folio (atómico)
     */
    public function siguienteFolio(): int
    {
        return DB::transaction(function () {
            $serie = static::lockForUpdate()->find($this->id);
            $folio = $serie->folio_actual + 1;
            $serie->update(['folio_actual' => $folio]);
            return $folio;
        });
    }

    public function getTipoLabelAttribute(): string
    {
        return match($this->tipo_comprobante) {
            'I' => 'Ingreso',
            'E' => 'Egreso',
            'P' => 'Pago',
            'N' => 'Nómina',
            default => $this->tipo_comprobante,
        };
    }

    /**
     * Serie por defecto para una empresa y tipo de comprobante
     */
    public static function defaultParaEmpresa(int $companyId, string $tipo = 'I'): ?self
    {
        return static::where('company_id', $companyId)
            ->where('tipo_comprobante', $tipo)
            ->where('activa', true)
            ->where('es_default', true)
            ->first()
            ?? static::where('company_id', $companyId)
                ->where('tipo_comprobante', $tipo)
                ->where('activa', true)
                ->first();
    }
}