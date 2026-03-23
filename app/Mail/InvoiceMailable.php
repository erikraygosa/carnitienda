<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class InvoiceMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string  $pdfRaw,
        public string  $pdfName,
        public string  $mensaje = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Factura ' . ($this->invoice->serie ?? '') . ($this->invoice->folio ?? $this->invoice->id),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfRaw, $this->pdfName)
                ->withMime('application/pdf'),
        ];
    }
}