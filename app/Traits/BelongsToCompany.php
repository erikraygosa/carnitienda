<?php

namespace App\Traits;

use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    /**
     * Asigna company_id automáticamente al crear si no viene explícito
     */
    protected static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $company = app(CompanyService::class)->activa();
                $model->company_id = $company?->id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope para filtrar por empresa activa
     */
    public function scopeDeEmpresaActiva($query)
    {
        $company = app(CompanyService::class)->activa();

        if ($company) {
            $query->where('company_id', $company->id);
        }

        return $query;
    }
}