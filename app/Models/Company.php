<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'razon_social',
        'nombre_comercial',
        'rfc',
        'tipo_persona',
        'telefono',
        'email',
        'sitio_web',
        'logo_path',
        'moneda',
        'pais',
        'timezone',
        'activo',
        'fecha_inicio',
        'fecha_vencimiento',
    ];

    protected $casts = [
        'activo'            => 'boolean',
        'fecha_inicio'      => 'date',
        'fecha_vencimiento' => 'date',
    ];

    // ------------------------------------------------------------------
    // Boot
    // ------------------------------------------------------------------
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Company $company) {
            if (empty($company->uuid)) {
                $company->uuid = Str::uuid()->toString();
            }
            if ($company->rfc) {
                $company->rfc = strtoupper(trim($company->rfc));
            }
        });

        static::updating(function (Company $company) {
            if ($company->isDirty('rfc')) {
                $company->rfc = strtoupper(trim($company->rfc));
            }
        });
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------
    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    // ------------------------------------------------------------------
    // Relaciones
    // ------------------------------------------------------------------
    public function fiscalData(): HasOne
    {
        return $this->hasOne(CompanyFiscalData::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(CompanyCertificate::class);
    }

    public function csdActivo(): HasOne
    {
        return $this->hasOne(CompanyCertificate::class)
            ->where('tipo', 'csd')
            ->where('activo', true)
            ->where('vigente', true)
            ->latest();
    }

    public function fielActiva(): HasOne
    {
        return $this->hasOne(CompanyCertificate::class)
            ->where('tipo', 'fiel')
            ->where('activo', true)
            ->where('vigente', true)
            ->latest();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot(['es_admin', 'es_empresa_activa'])
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('es_admin', true);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    public function tieneCsd(): bool
    {
        return $this->csdActivo()->exists();
    }

    public function tieneFiel(): bool
    {
        return $this->fielActiva()->exists();
    }

    public function tieneConfiguracionCompleta(): bool
    {
        return $this->fiscalData()->exists() && $this->tieneCsd();
    }

    public function getNombreDisplayAttribute(): string
    {
        return $this->nombre_comercial ?? $this->razon_social;
    }

    public function getEsPersonaMoralAttribute(): bool
    {
        return $this->tipo_persona === 'moral';
    }

    public function getEsPersonaFisicaAttribute(): bool
    {
        return $this->tipo_persona === 'fisica';
    }

    public static function validarRfc(string $rfc): bool
    {
        $patron = '/^([A-ZÑ&]{3,4})(\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])([A-Z\d]{2})([A\d])$/';
        return (bool) preg_match($patron, strtoupper(trim($rfc)));
    }
}