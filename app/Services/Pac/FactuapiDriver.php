<?php

namespace App\Services\Pac;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceComplementDoc;
use App\Models\InvoiceSeries;
use App\Models\PacConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FactuapiDriver implements PacDriverInterface
{
    protected PacConfiguration $config;

    // URLs de Factuapi
        const URL_SANDBOX    = 'https://www.facturapi.io/v2';
const URL_PRODUCCION = 'https://www.facturapi.io/v2';
    

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
        if ($invoice->tipo_comprobante === 'P') {
            return $this->buildPaymentComplementPayload($invoice, $company);
        }

        return $this->buildIngresoPayload($invoice, $company);
    }

    protected function buildPaymentComplementPayload(Invoice $invoice, Company $company): array
    {
        $cliente = $invoice->client;
        $payment = $invoice->arPayment?->load('paymentType');
        $docs    = $invoice->complementDocs()->with('relatedInvoice.items')->get();

        $relatedDocuments = $docs->map(function (InvoiceComplementDoc $doc) {
            $ri = $doc->relatedInvoice;

            // Tasa IVA promedio de los conceptos de la factura original
            $tasaIva  = (float) ($ri->items->avg('iva_pct')  ?? 16);
            $tasaIeps = (float) ($ri->items->avg('ieps_pct') ?? 0);
            $tasaIvaDecimal  = round($tasaIva  / 100, 4);
            $tasaIepsDecimal = round($tasaIeps / 100, 4);

            $base = $tasaIva > 0
                ? round((float) $doc->imp_pagado / (1 + $tasaIvaDecimal), 2)
                : (float) $doc->imp_pagado;

            $taxes = [];
            if ($tasaIva > 0) {
                $taxes[] = [
                    'base'   => $base,
                    'type'   => 'IVA',
                    'rate'   => $tasaIvaDecimal,
                    'factor' => 'Tasa',
                ];
            }
            if ($tasaIeps > 0) {
                $taxes[] = [
                    'base'   => $base,
                    'type'   => 'IEPS',
                    'rate'   => $tasaIepsDecimal,
                    'factor' => 'Tasa',
                ];
            }

            return [
                'uuid'         => $ri->uuid,
                'amount'       => (float) $doc->imp_pagado,
                'installment'  => $doc->num_parcialidad,
                'last_balance' => (float) $doc->imp_saldo_anterior,
                'taxes'        => $taxes,
            ];
        })->values()->toArray();

        $paymentDate = $payment?->fecha
            ? \Carbon\Carbon::parse($payment->fecha)->startOfDay()->toIso8601String()
            : now()->toIso8601String();

        return [
            'type'     => 'P',
            'customer' => [
                'legal_name' => $cliente?->razon_social ?? $cliente?->nombre ?? 'PÚBLICO EN GENERAL',
                'tax_id'     => $cliente?->rfc ?? 'XAXX010101000',
                'tax_system' => $invoice->regimen_fiscal_receptor ?? '616',
                'address'    => [
                    'zip' => $cliente?->fiscal_cp ?? $cliente?->cp ?? '06600',
                ],
            ],
            'complements' => [[
                'type' => 'pago',
                'data' => [[
                    'payment_form'       => $payment?->paymentType?->satFormaPago() ?? '99',
                    'date'               => $paymentDate,
                    'related_documents'  => $relatedDocuments,
                ]],
            ]],
        ];
    }

    protected function buildIngresoPayload(Invoice $invoice, Company $company): array
    {
    $fiscalData = $company->fiscalData;
    $cliente    = $invoice->client;

    $folio = $invoice->folio ?? null;
$serie = $invoice->serie ?? 'A';

    $items = $invoice->items->map(function ($item) {
        $impuestos = [];

        if ((float)$item->iva_pct > 0) {
            $impuestos[] = [
                'type'   => 'IVA',
                'rate'   => round((float)$item->iva_pct / 100, 4),
            ];
        }

        if ((float)$item->ieps_pct > 0) {
            $impuestos[] = [
                'type'   => 'IEPS',
                'rate'   => round((float)$item->ieps_pct / 100, 4),
            ];
        }

        $productData = [
            'description' => $item->descripcion,
            'product_key' => $item->clave_prod_serv ?? '01010101',
            'price'       => (float)$item->valor_unitario,
        ];

        if (! empty($impuestos)) {
            $productData['taxes'] = $impuestos;
        }

        $itemData = [
            'quantity' => (float)$item->cantidad,
            'product'  => $productData,
        ];

        if ((float)$item->descuento > 0) {
            $itemData['discount'] = (float)$item->descuento;
        }

        return $itemData;
    })->values()->toArray();

    $payload = [
        'customer' => [
            'legal_name' => $cliente?->razon_social ?? $cliente?->nombre ?? 'PUBLICO EN GENERAL',
            'tax_id'     => $cliente?->rfc ?? 'XAXX010101000',
            'tax_system' => $invoice->regimen_fiscal_receptor ?? '616',
            'address'    => [
                'zip' => $cliente?->fiscal_cp ?? $cliente?->cp ?? '06600',
            ],
        ],
        'items'          => $items,
        'payment_form'   => $invoice->forma_pago ?? '99',
        'payment_method' => $invoice->metodo_pago ?? 'PUE',
        'use'            => $invoice->uso_cfdi ?? 'G03',
    ];

        if ($invoice->serie) {
    $payload['series'] = $invoice->serie;
}

    if ($folio) {
        $payload['folio_number'] = $folio;
    }

    return $payload;
}
}