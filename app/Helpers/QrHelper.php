<?php

namespace App\Helpers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrHelper
{
    /**
     * Genera un QR en base64 PNG usando bacon/bacon-qr-code v3
     * que ya está instalado en el proyecto.
     */
    public static function base64Png(string $text, int $size = 80): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $svg    = $writer->writeString($text);

        // Devolvemos el SVG como base64 (DomPDF soporta SVG embebido)
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}