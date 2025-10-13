<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappSender
{
    public function sendPdf(string $telefono, string $mensaje, string $filename, string $pdfRaw): array
    {
        $url = rtrim(config('services.whatsapp.url', env('WHATSAPP_API_URL')), '/');

        $payload = [
            'telefono'   => $this->normalizePhone($telefono),
            'mensaje'    => $mensaje,
            'media_b64'  => base64_encode($pdfRaw), // SIN prefijo, tal cual pide tu API
            'filename'   => $filename,
            'mimetype'   => 'application/pdf',
        ];

        $req = Http::withHeaders($this->authHeaders())->post($url, $payload);

        return [
            'ok'     => $req->successful(),
            'status' => $req->status(),
            'body'   => $req->json() ?: $req->body(),
        ];
    }

    protected function authHeaders(): array
    {
        $header = trim((string) env('WHATSAPP_AUTH_HEADER'));
        $value  = trim((string) env('WHATSAPP_AUTH_VALUE'));

        if ($header !== '' && $value !== '') {
            return [$header => $value, 'Content-Type' => 'application/json'];
        }
        return ['Content-Type' => 'application/json'];
    }

    protected function normalizePhone(string $raw): string
    {
        // Mantén sólo dígitos; si tu API requiere “521” o similar, asegúrate de que el cliente ya tenga el formato correcto.
        return preg_replace('/\D+/', '', $raw);
    }
}
