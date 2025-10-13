<?php

namespace App\Mail;

use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesOrderDeliveryNoteMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SalesOrder $order, public string $pdfRaw, public string $filename){}

    public function build()
    {
        return $this->subject('Remisión de pedido #'.$this->order->id)
            ->view('emails.sales_order_delivery_note')
            ->attachData($this->pdfRaw, $this->filename, ['mime'=>'application/pdf']);
    }
}
