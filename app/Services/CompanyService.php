<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CompanyService
{
    /**
     * Devuelve la empresa activa del usuario autenticado.
     * Si solo hay una empresa en el sistema, la retorna directamente.
     * Cachea por sesión para no golpear BD en cada request.
     */
        public function activa(): ?Company
{
    if (! Auth::check()) {
        return null;
    }
    $userId = Auth::id();
    return Cache::remember("company_activa_{$userId}", 60, function () use ($userId) {
        // 1) Empresa marcada como activa para este usuario
        $company = Company::with('fiscalData')
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->where('es_empresa_activa', true);
            })->where('activo', true)->first();
        if ($company) return $company;

        // 2) Fallback: primera empresa a la que pertenece el usuario
        $company = Company::with('fiscalData')
            ->whereHas('users', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })->where('activo', true)->first();
        if ($company) return $company;

        // 3) Fallback final
        return Company::with('fiscalData')->where('activo', true)->first();
    });
}

    /**
     * Limpia el cache cuando el usuario cambia de empresa
     */
    public function limpiarCache(): void
    {
        if (Auth::check()) {
            Cache::forget('company_activa_' . Auth::id());
        }
    }

    /**
     * Cambia la empresa activa del usuario
     */
    public function cambiarEmpresa(int $companyId): bool
    {
        if (! Auth::check()) return false;

        $userId = Auth::id();

        // Desactivar todas
        \DB::table('company_user')
            ->where('user_id', $userId)
            ->update(['es_empresa_activa' => false]);

        // Activar la seleccionada
        $updated = \DB::table('company_user')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->update(['es_empresa_activa' => true]);

        $this->limpiarCache();

        return $updated > 0;
    }
}