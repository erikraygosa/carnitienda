<?php

namespace App\Mail;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SaleNoteMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Sale $sale, public string $pdfRaw, public string $filename) {}

    public function build()
    {
        return $this->subject('Nota de venta '.$this->sale->folio)
            ->view('emails.sales_note')
            ->attachData($this->pdfRaw, $this->filename, ['mime'=>'application/pdf']);
    }
}
