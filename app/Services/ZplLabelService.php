<?php

namespace App\Services;

use App\Models\SystemSetting;

class ZplLabelService
{
    /**
     * ¿Está el sistema en modo etiqueta ZPL (en vez de ticket normal)?
     */
    public function modoZplActivo(): bool
    {
        return SystemSetting::get('etiquetas.modo_impresion', 'ticket') === 'zpl';
    }

    /**
     * ¿Se imprime una etiqueta por cada caja (pidiendo el peso de cada una)?
     */
    public function imprimirPorCajas(): bool
    {
        return (bool) SystemSetting::get('etiquetas.imprimir_por_cajas', false);
    }

    /**
     * Arma el ZPL de UNA etiqueta de producto surtido.
     * Tamaño pensado para una etiqueta de 4"x3" a 203dpi (812x609 dots) —
     * suficiente para folio, cliente, producto, caja y peso sin saturarla.
     *
     * @param array{folio:string,cliente:string,producto:string,cantidad?:string,caja_num?:int,caja_total?:int,peso?:float,fecha?:string} $datos
     */
    public function construirEtiqueta(array $datos): string
    {
        $folio     = $this->zplEscape($datos['folio'] ?? '');
        $cliente   = $this->zplEscape($datos['cliente'] ?? '');
        $producto  = $this->zplEscape($datos['producto'] ?? '');
        $cantidad  = $this->zplEscape($datos['cantidad'] ?? '');
        $fecha     = $this->zplEscape($datos['fecha'] ?? now()->format('d/m/Y H:i'));
        $cajaNum   = $datos['caja_num'] ?? null;
        $cajaTotal = $datos['caja_total'] ?? null;
        $peso      = $datos['peso'] ?? null;

        $zpl  = "^XA\n";
        $zpl .= "^CI28\n"; // UTF-8, para acentos/ñ
        $zpl .= "^PW812\n";
        $zpl .= "^LL609\n";

        $zpl .= "^FO30,20^A0N,45,45^FD{$folio}^FS\n";
        $zpl .= "^FO30,80^GB752,3,3^FS\n"; // línea separadora

        $zpl .= "^FO30,100^A0N,30,30^FDCliente:^FS\n";
        $zpl .= "^FO230,100^A0N,30,30^FD{$cliente}^FS\n";

        $zpl .= "^FO30,150^A0N,30,30^FDProducto:^FS\n";
        $zpl .= "^FO30,190^A0N,35,35^FD{$producto}^FS\n";

        if ($cantidad !== '') {
            $zpl .= "^FO30,250^A0N,28,28^FDCantidad: {$cantidad}^FS\n";
        }

        if ($cajaNum !== null && $cajaTotal !== null) {
            $zpl .= "^FO30,320^A0N,60,60^FDCaja {$cajaNum} de {$cajaTotal}^FS\n";
        }

        if ($peso !== null) {
            $pesoStr = $this->zplEscape(number_format((float) $peso, 3) . ' kg');
            $zpl .= "^FO30,400^A0N,55,55^FDPeso: {$pesoStr}^FS\n";
        }

        $zpl .= "^FO30,470^A0N,22,22^FD{$fecha}^FS\n";

        // Código de barras del folio, útil para escanear en recepción.
        if ($folio !== '') {
            $zpl .= "^FO30,510^BY2\n";
            $zpl .= "^BCN,60,Y,N,N\n";
            $zpl .= "^FD{$folio}^FS\n";
        }

        $zpl .= "^XZ\n";

        return $zpl;
    }

    /**
     * Manda uno o varios bloques ZPL ya armados a la impresora configurada,
     * concatenados en una sola conexión de socket (más rápido que abrir/cerrar
     * una conexión por etiqueta cuando son varias cajas).
     *
     * @param string[] $etiquetas
     * @return array{ok: bool, message: string}
     */
    public function enviar(array $etiquetas): array
    {
        $ip     = trim((string) SystemSetting::get('etiquetas.impresora_ip', ''));
        $puerto = (int) SystemSetting::get('etiquetas.impresora_puerto', 9100);

        if ($ip === '') {
            return ['ok' => false, 'message' => 'No hay IP de impresora configurada — ve a SuperAdmin → Configuración → Etiquetas.'];
        }

        if (empty($etiquetas)) {
            return ['ok' => false, 'message' => 'No hay etiquetas para imprimir.'];
        }

        $payload = implode('', $etiquetas);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ip, $puerto, $errno, $errstr, 5);

        if ($socket === false) {
            return ['ok' => false, 'message' => "No se pudo conectar a la impresora {$ip}:{$puerto} — {$errstr} (verifica que esté encendida y en la misma red)."];
        }

        $bytesEscritos = @fwrite($socket, $payload);
        fclose($socket);

        if ($bytesEscritos === false || $bytesEscritos < strlen($payload)) {
            return ['ok' => false, 'message' => 'La conexión se hizo pero el envío de datos falló a la mitad — intenta de nuevo.'];
        }

        $n = count($etiquetas);
        return ['ok' => true, 'message' => $n === 1 ? '1 etiqueta enviada a la impresora.' : "{$n} etiquetas enviadas a la impresora."];
    }

    /**
     * Escapa caracteres que rompen el ZPL (^ y ~ son delimitadores de comando).
     */
    private function zplEscape(?string $texto): string
    {
        return str_replace(['^', '~'], ['', ''], (string) $texto);
    }
}
