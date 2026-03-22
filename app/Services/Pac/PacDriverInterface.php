<?php

namespace App\Services\Pac;

use App\Models\Company;
use App\Models\Invoice;

interface PacDriverInterface
{
    /**
     * Construye el XML sin timbrar (para preview o firma manual)
     */
    public function buildXml(Invoice $invoice, Company $company): string;

    /**
     * Timbra el CFDI y devuelve uuid, xml_timbrado, sello_cfdi, sello_sat
     */
    public function stamp(Invoice $invoice, string $xmlSinSellar, Company $company): array;

    /**
     * Cancela un CFDI ya timbrado
     */
    public function cancel(Invoice $invoice, string $motivo, ?string $folioSustitucion = null): array;

    /**
     * Verifica el estado de un CFDI en el SAT
     */
    public function status(Invoice $invoice): array;

    /**
     * Nombre del driver para logs y UI
     */
    public function nombre(): string;
}