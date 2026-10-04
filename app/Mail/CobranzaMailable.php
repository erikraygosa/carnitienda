<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CobranzaMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $pdfRaw,
        public string $pdfName,
        public array  $resumen,
        public array  $totales,
        public array  $filtros,
        public string $mensaje = '',
        public mixed  $empresa = null,
    ) {}

    public function envelope(): Envelope
    {
        $nombre = count($this->resumen) === 1 ? ' — ' . $this->resumen[0]['cliente'] : '';
        $emp = $this->empresa?->nombre_comercial ?: $this->empresa?->razon_social ?: config('app.name');

        return new Envelope(subject: 'Estado de cuenta' . $nombre . ' · ' . $emp);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.cobranza');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfRaw, $this->pdfName)->withMime('application/pdf'),
        ];
    }
}
