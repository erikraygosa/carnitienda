<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuotePdfMailable extends Mailable
{
    use Queueable, SerializesModels;

    public Quote $quote;
    public string $pdfRaw;
    public string $filename;

    public function __construct(Quote $quote, string $pdfRaw, string $filename = 'cotizacion.pdf')
    {
        $this->quote    = $quote;
        $this->pdfRaw   = $pdfRaw;
        $this->filename = $filename;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Cotización '.$this->quote->id.' - '.$this->quote->client?->nombre)
            ->view('emails.quotes.send') // vista simple con texto
            ->attachData($this->pdfRaw, $this->filename, ['mime' => 'application/pdf']);
    }
}
