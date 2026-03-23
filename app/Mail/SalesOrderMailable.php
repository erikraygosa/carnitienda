<?php

namespace App\Mail;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class SalesOrderMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SalesOrder $order,
        public string     $pdfRaw,
        public string     $pdfName,
        public string     $mensaje = '',
        public mixed      $empresa = null,
    ) {
        $this->mensaje = $mensaje ?? '';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Remisión de pedido ' . $this->order->folio,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sales_order',
            with: [
                'order'   => $this->order,
                'mensaje' => $this->mensaje,
                'empresa' => $this->empresa,
            ],
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