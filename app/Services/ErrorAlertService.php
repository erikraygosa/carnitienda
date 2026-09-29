<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class ErrorAlertService
{
    public function __construct(private WhatsappSender $whatsapp) {}

    public function notify(\Throwable $e): void
    {
        // Solo errores reales de la app vista por un usuario (peticiones
        // HTTP) — un error que truena corriendo `artisan tinker`/comandos
        // por SSH (ej. una prueba de diagnóstico) no debe mandar alerta,
        // eso no lo ve ningún cliente ni usuario del sistema.
        if (app()->runningInConsole()) {
            return;
        }

        $numero = SystemSetting::get('whatsapp.numero_errores');
        if (blank($numero) || !$this->whatsapp->isConfigured()) {
            return;
        }

        // No mandar el mismo error dos veces en un ratito — si algo se
        // rompe en ráfaga (varios usuarios pegándole a la misma ruta),
        // solo se avisa una vez cada 10 minutos por clase+archivo+línea.
        $clave = 'error_alert:' . md5(get_class($e) . '|' . $e->getFile() . '|' . $e->getLine());
        if (Cache::has($clave)) {
            return;
        }
        Cache::put($clave, true, now()->addMinutes(10));

        $request = request();

        $mensaje = "⚠️ *Error 500 en " . config('app.name') . "*\n\n"
            . '*Excepción:* ' . get_class($e) . "\n"
            . '*Mensaje:* ' . $e->getMessage() . "\n"
            . '*Archivo:* ' . $this->rutaCorta($e->getFile()) . ':' . $e->getLine() . "\n"
            . ($request ? '*URL:* ' . $request->method() . ' ' . $request->fullUrl() . "\n" : '')
            . ($request?->user() ? '*Usuario:* ' . $request->user()->name . " (#{$request->user()->id})\n" : '')
            . '*Fecha:* ' . now()->format('d/m/Y H:i:s');

        $this->whatsapp->sendText($numero, $mensaje);
    }

    private function rutaCorta(string $path): string
    {
        return str_replace(base_path() . '/', '', $path);
    }
}
