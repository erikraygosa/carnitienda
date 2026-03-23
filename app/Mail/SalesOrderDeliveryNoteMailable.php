<?php
namespace App\Mail;
use App\Models\SalesOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SalesOrderDeliveryNoteMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SalesOrder $order,
        public string     $pdfRaw,
        public string     $filename,
        public string     $mensaje = '',
        public mixed      $empresa = null,
    ) {
        $this->mensaje = $mensaje ?? '';
    }

    public function build()
    {
        return $this->subject('Remisión de pedido ' . $this->order->folio)
            ->view('emails.sales_order')
            ->with([
                'order'   => $this->order,
                'mensaje' => $this->mensaje,
                'empresa' => $this->empresa,
            ])
            ->attachData($this->pdfRaw, $this->filename, ['mime' => 'application/pdf']);
    }
}