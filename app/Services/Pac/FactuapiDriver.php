<?php

namespace App\Services\Pac;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceSeries;
use App\Models\PacConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FactuapiDriver implements PacDriverInterface
{
    protected PacConfiguration $config;

    // URLs de Factuapi
    const URL_SANDBOX    = 'https://apisandbox.factuapi.io/v2';
    const URL_PRODUCCION = 'https://api.factuapi.io/v2';

    public function __construct(PacConfiguration $config)
    {
        $this->config = $config;
    }

    protected function baseUrl(): string
    {
        return $this->config->esSandbox()
            ? self::URL_SANDBOX
            : self::URL_PRODUCCION;
    }

    protected function apiKey(): string
    {
        return $this->config->getApiKey() ?? '';
    }

    protected function http()
    {
        return Http::withToken($this->apiKey())
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout(30);
    }

    public function nombre(): string
    {
        return 'Factuapi';
    }

    // ------------------------------------------------------------------
    // Build XML
    // ------------------------------------------------------------------
    public function buildXml(Invoice $invoice, Company $company): string
    {
        // Factuapi construye y timbra en un solo paso
        // Este método retorna el payload JSON que se enviará
        return json_encode($this->buildPayload($invoice, $company));
    }

    // ------------------------------------------------------------------
    // Timbrar
    // ------------------------------------------------------------------
    public function stamp(Invoice $invoice, string $xmlSinSellar, Company $company): array
    {
        try {
            $payload = $this->buildPayload($invoice, $company);

            $response = $this->http()
                ->post('/invoices', $payload);

            if ($response->failed()) {
                $error = $response->json('message')
                    ?? $response->json('error')
                    ?? 'Error desconocido de Factuapi';

                Log::error('Factuapi stamp error', [
                    'invoice_id' => $invoice->id,
                    'status'     => $response->status(),
                    'body'       => $response->body(),
                ]);

                return ['ok' => false, 'error' => $error];
            }

            $data = $response->json();

            return [
                'ok'                    => true,
                'uuid'                  => $data['uuid'] ?? null,
                'xml_timbrado'          => $data['xml'] ?? null,
                'numero_certificado_sat'=> $data['stamp']['sat_cert_number'] ?? null,
                'sello_cfdi'            => $data['stamp']['cfdi_sign'] ?? null,
                'sello_sat'             => $data['stamp']['sat_sign'] ?? null,
                'factuapi_id'           => $data['id'] ?? null,
            ];

        } catch (\Throwable $e) {
            Log::error('Factuapi stamp exception', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Cancelar
    // ------------------------------------------------------------------
    public function cancel(Invoice $invoice, string $motivo, ?string $folioSustitucion = null): array
    {
        try {
            if (! $invoice->uuid) {
                return ['ok' => false, 'error' => 'La factura no tiene UUID para cancelar.'];
            }

            $payload = ['motive' => $motivo];
            if ($folioSustitucion) {
                $payload['substitution_uuid'] = $folioSustitucion;
            }

            $response = $this->http()
                ->delete("/invoices/{$invoice->uuid}", $payload);

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Error al cancelar en Factuapi';
                return ['ok' => false, 'error' => $error];
            }

            return ['ok' => true];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Estado en SAT
    // ------------------------------------------------------------------
    public function status(Invoice $invoice): array
    {
        try {
            if (! $invoice->uuid) {
                return ['ok' => false, 'error' => 'Sin UUID'];
            }

            $response = $this->http()
                ->get("/invoices/{$invoice->uuid}/status");

            if ($response->failed()) {
                return ['ok' => false, 'error' => $response->json('message') ?? 'Error'];
            }

            return ['ok' => true, 'data' => $response->json()];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------
    // Construir payload para Factuapi
    // ------------------------------------------------------------------
    protected function buildPayload(Invoice $invoice, Company $company): array
    {
        $fiscalData = $company->fiscalData;
        $cliente    = $invoice->client;

        // Obtener siguiente folio de la serie
        $serie = InvoiceSeries::defaultParaEmpresa(
            $company->id,
            $invoice->tipo_comprobante ?? 'I'
        );

        $items = $invoice->items->map(function ($item) {
            $impuestos = [];

            if ((float)$item->iva_pct > 0) {
                $impuestos[] = [
                    'type'   => 'IVA',
                    'rate'   => (float)$item->iva_pct / 100,
                    'factor' => 'Tasa',
                ];
            }

            if ((float)$item->ieps_pct > 0) {
                $impuestos[] = [
                    'type'   => 'IEPS',
                    'rate'   => (float)$item->ieps_pct / 100,
                    'factor' => 'Tasa',
                ];
            }

            return array_filter([
                'quantity'          => (float)$item->cantidad,
                'product_code'      => $item->clave_prod_serv ?? '01010101',
                'unit_code'         => $item->clave_unidad ?? 'H87',
                'unit'              => $item->unidad ?? 'Pieza',
                'description'       => $item->descripcion,
                'price'             => (float)$item->valor_unitario,
                'discount'          => (float)$item->descuento > 0 ? (float)$item->descuento : null,
                'tax_included'      => false,
                'taxability'        => $item->objeto_imp ?? '02',
                'taxes'             => ! empty($impuestos) ? $impuestos : null,
            ]);
        })->values()->toArray();

        return array_filter([
            'type'                => $invoice->tipo_comprobante ?? 'I',
            'series'              => $serie?->serie ?? $invoice->serie ?? 'A',
            'folio_number'        => $serie ? $serie->siguienteFolio() : null,
            'date'                => optional($invoice->fecha)->format('Y-m-d\TH:i:s'),
            'payment_form'        => $invoice->forma_pago ?? '99',
            'payment_method'      => $invoice->metodo_pago ?? 'PUE',
            'currency'            => $invoice->moneda ?? 'MXN',
            'expedition_place'    => $invoice->lugar_expedicion ?? $fiscalData?->codigo_postal,
            'cfdi_use'            => $invoice->uso_cfdi ?? 'G03',
            'export'              => $invoice->exportacion ?? '01',

            'issuer' => array_filter([
                'tax_id'      => $company->rfc,
                'name'        => $company->razon_social,
                'tax_system'  => $invoice->regimen_fiscal_emisor ?? $fiscalData?->regimen_fiscal,
            ]),

            'recipient' => array_filter([
                'tax_id'      => $cliente?->rfc ?? 'XAXX010101000',
                'name'        => $cliente?->razon_social ?? $cliente?->nombre ?? 'PUBLICO EN GENERAL',
                'zip'         => $cliente?->fiscal_cp ?? $cliente?->cp ?? '00000',
                'tax_system'  => $invoice->regimen_fiscal_receptor ?? '616',
                'use'         => $invoice->uso_cfdi ?? 'G03',
            ]),

            'items' => $items,
        ]);
    }
}