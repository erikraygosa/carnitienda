<?php
namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuotePdfMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
    public Quote  $quote,
    public string $pdfRaw,
    public string $filename = 'cotizacion.pdf',
    public string $mensaje  = '',
    public mixed  $empresa  = null,
) {
    $this->mensaje = $mensaje ?? '';
}

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Cotización ' . ($this->quote->folio ?? $this->quote->id) . ' — ' . $this->quote->client?->nombre)
            ->view('emails.quote')
            ->with([
                'quote'   => $this->quote,
                'mensaje' => $this->mensaje,
                'empresa' => $this->empresa,
            ])
            ->attachData($this->pdfRaw, $this->filename, ['mime' => 'application/pdf']);
    }
}