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

            // El XML timbrado no viene en la respuesta de creación — hay que
            // pedirlo aparte al endpoint /invoices/{id}/xml (devuelve XML crudo,
            // no JSON).
            $xmlTimbrado = null;
            if (! empty($data['id'])) {
                $xmlResponse = $this->http()->get("/invoices/{$data['id']}/xml");
                if ($xmlResponse->successful()) {
                    $xmlTimbrado = $xmlResponse->body();
                }
            }

            return [
                'ok'                     => true,
                'uuid'                   => $data['uuid'] ?? null,
                'xml_timbrado'           => $xmlTimbrado,
                // Nombres reales del JSON de Facturapi (verificado contra su API):
                // stamp.signature = sello digital del emisor, stamp.sat_signature = sello del SAT.
                // Los nombres anteriores (cfdi_sign / sat_sign) no existen en su respuesta
                // y por eso estos dos campos siempre se guardaban en NULL.
                'numero_certificado_sat' => $data['stamp']['sat_cert_number'] ?? null,
                'sello_cfdi'             => $data['stamp']['signature'] ?? null,
                'sello_sat'              => $data['stamp']['sat_signature'] ?? null,
                'rfc_provider_cert'      => $data['stamp']['rfc_provider_cert'] ?? null,
                'factuapi_id'            => $data['id'] ?? null,
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
            $facturapiId = $this->resolveFacturapiId($invoice);
            if (! $facturapiId) {
                return ['ok' => false, 'error' => 'No se pudo identificar el documento en Facturapi (sin factuapi_id ni UUID para buscarlo).'];
            }

            // Facturapi espera motive/substitution como query params del DELETE,
            // no en el cuerpo — Http::delete($url, $data) manda $data como
            // JSON body por default, así que aquí nunca le llegaba el motivo
            // real (y "substitution_uuid" tampoco es el nombre que usa la API,
            // es "substitution"). Verificado contra la doc oficial de Facturapi.
            $query = ['motive' => $motivo];
            if ($folioSustitucion) {
                $query['substitution'] = $folioSustitucion;
            }

            $response = $this->http()
                ->withQueryParameters($query)
                ->delete("/invoices/{$facturapiId}");

            if ($response->failed()) {
                $error = $response->json('message') ?? 'Error al cancelar en Factuapi';
                return ['ok' => false, 'error' => $error];
            }

            $data = $response->json();

            // Facturapi no siempre cancela de inmediato: cuando el motivo/monto
            // lo exige ante el SAT, el receptor debe aceptar o rechazar la
            // cancelación (hasta 72h). En ese caso el HTTP responde 200 pero
            // el CFDI sigue "valid" con cancellation_status "pending" — NO es
            // una cancelación definitiva y no debe tratarse como tal.
            // status: 'valid' | 'canceled'
            // cancellation_status: 'none' | 'pending' | 'accepted' | 'rejected' | 'expired'
            return [
                'ok'                  => true,
                'status'              => $data['status'] ?? 'canceled',
                'cancellation_status' => $data['cancellation_status'] ?? 'accepted',
            ];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Error de conexión: ' . $e->getMessage()];
        }
    }

    /**
     * El endpoint de cancelar/estatus necesita el ID interno de Facturapi
     * (tipo ObjectId), NO el UUID fiscal del SAT — son formatos distintos.
     * stamp() ya lo guarda en 'factuapi_id' para facturas nuevas; para las
     * timbradas antes de ese fix (factuapi_id nulo) se recupera buscando
     * por UUID en Facturapi y se guarda para la próxima vez.
     */
    protected function resolveFacturapiId(Invoice $invoice): ?string
    {
        if ($invoice->factuapi_id) {
            return $invoice->factuapi_id;
        }

        if (! $invoice->uuid) {
            return null;
        }

        $resp = $this->http()->get('/invoices', ['uuid' => $invoice->uuid]);
        if ($resp->failed()) {
            return null;
        }

        $id = $resp->json('data.0.id');
        if ($id) {
            $invoice->update(['factuapi_id' => $id]);
        }

        return $id;
    }

    // ------------------------------------------------------------------
    // Estado en SAT
    // ------------------------------------------------------------------
    public function status(Invoice $invoice): array
    {
        try {
            $facturapiId = $this->resolveFacturapiId($invoice);
            if (! $facturapiId) {
                return ['ok' => false, 'error' => 'No se pudo identificar el documento en Facturapi'];
            }

            $response = $this->http()
                ->get("/invoices/{$facturapiId}/status");

            if ($response->failed()) {
                return ['ok' => false, 'error' => $response->json('message') ?? 'Error'];
            }

            $data = $response->json();

            return [
                'ok'                  => true,
                'status'              => $data['status'] ?? null,
                'cancellation_status' => $data['cancellation_status'] ?? null,
                'data'                => $data,
            ];

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
        'type'     => $invoice->tipo_comprobante === 'E' ? 'E' : 'I',
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

    // Nota de crédito: relacionar con el CFDI de Ingreso que se está afectando
    if ($invoice->tipo_comprobante === 'E' && $invoice->relatedInvoiceOriginal?->uuid) {
        $payload['related_documents'] = [[
            'relationship' => '01', // Nota de crédito de los documentos relacionados
            'documents'    => $invoice->relatedInvoiceOriginal->uuid,
        ]];
    }

    return $payload;
}
}