<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampCounter extends Model
{
    protected $fillable = [
        'company_id',
        'timbres_contratados',
        'timbres_usados',
        'timbres_cancelados',
        'vigencia_inicio',
        'vigencia_fin',
        'activo',
        'notas',
    ];

    protected $casts = [
        'activo'          => 'boolean',
        'vigencia_inicio' => 'date',
        'vigencia_fin'    => 'date',
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
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeVigente($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('vigencia_fin')
              ->orWhere('vigencia_fin', '>=', now()->toDateString());
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public function timbresRestantes(): int
    {
        return max(0, $this->timbres_contratados - $this->timbres_usados);
    }

    public function tieneTimbres(): bool
    {
        return $this->timbresRestantes() > 0;
    }

    public function porcentajeUsado(): float
    {
        if ($this->timbres_contratados === 0) return 0;
        return round(($this->timbres_usados / $this->timbres_contratados) * 100, 1);
    }

    public function alertaRestantes(): ?string
    {
        $restantes = $this->timbresRestantes();
        $alerta    = (int) SystemSetting::get('facturacion.alerta_timbres', 50);

        if ($restantes === 0)         return 'agotado';
        if ($restantes <= $alerta)    return 'critico';
        if ($restantes <= $alerta * 2) return 'advertencia';
        return null;
    }

    /**
     * Registra un timbre usado (llamar después de timbrar exitosamente)
     */
    public function registrarTimbre(): void
    {
        $this->increment('timbres_usados');
    }

    /**
     * Registra un timbre cancelado (no recupera el timbre, solo es contable)
     */
    public function registrarCancelacion(): void
    {
        $this->increment('timbres_cancelados');
    }

    /**
     * Obtiene el contador activo y vigente de una empresa
     */
    public static function activoParaEmpresa(int $companyId): ?self
    {
        return static::where('company_id', $companyId)
            ->activo()
            ->vigente()
            ->latest()
            ->first();
    }
}