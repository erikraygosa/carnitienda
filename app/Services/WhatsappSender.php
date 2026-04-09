<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappSender
{
    private string $baseUrl;
    private string $instance;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl  = rtrim(env('EVO_API_BASE_URL', ''), '/');
        $this->instance = env('EVO_API_INSTANCE', '');
        $this->apiKey   = env('EVO_API_KEY', '');
    }

    public function sendPdf(string $telefono, string $mensaje, string $filename, string $pdfRaw): array
    {
        $phone = $this->normalizePhone($telefono);

        // 1. Primero enviamos el PDF como media
        $mediaUrl = "{$this->baseUrl}/message/sendMedia/{$this->instance}";

        $payload = [
            'number'    => $phone,
            'mediatype' => 'document',
            'mimetype'  => 'application/pdf',
            'caption'   => $mensaje,
            'media'     => base64_encode($pdfRaw),
            'fileName'  => $filename,
        ];

        $req = Http::withHeaders($this->authHeaders())
            ->post($mediaUrl, $payload);

        return [
            'ok'     => $req->successful(),
            'status' => $req->status(),
            'body'   => $req->json() ?: $req->body(),
        ];
    }

    public function sendText(string $telefono, string $mensaje): array
    {
        $phone = $this->normalizePhone($telefono);
        $url   = "{$this->baseUrl}/message/sendText/{$this->instance}";

        $req = Http::withHeaders($this->authHeaders())
            ->post($url, [
                'number'  => $phone,
                'text'    => $mensaje,
            ]);

        return [
            'ok'     => $req->successful(),
            'status' => $req->status(),
            'body'   => $req->json() ?: $req->body(),
        ];
    }

    protected function authHeaders(): array
    {
        return [
            'apikey'       => $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    protected function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);

        // EvoAPI requiere formato internacional sin '+'
        // México: si empieza con 52 ya está bien, si no agrégalo
        if (strlen($digits) === 10) {
            $digits = '52' . $digits;
        }

        return $digits;
    }
}