<?php

namespace App\Services\Pac;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\PacConfiguration;

/**
 * Driver genérico — placeholder para el segundo PAC.
 * Implementar cuando llegue la documentación.
 */
class GenericPacDriver implements PacDriverInterface
{
    protected PacConfiguration $config;

    public function __construct(PacConfiguration $config)
    {
        $this->config = $config;
    }

    public function nombre(): string
    {
        return $this->config->nombre ?? 'PAC Alternativo';
    }

    public function buildXml(Invoice $invoice, Company $company): string
    {
        throw new \RuntimeException('GenericPacDriver: buildXml no implementado aún.');
    }

    public function stamp(Invoice $invoice, string $xmlSinSellar, Company $company): array
    {
        return [
            'ok'    => false,
            'error' => 'PAC alternativo no configurado. Agrega las credenciales en Superadmin → PAC.',
        ];
    }

    public function cancel(Invoice $invoice, string $motivo, ?string $folioSustitucion = null): array
    {
        return ['ok' => false, 'error' => 'PAC alternativo no implementado.'];
    }

    public function status(Invoice $invoice): array
    {
        return ['ok' => false, 'error' => 'PAC alternativo no implementado.'];
    }
}