<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id','product_id','descripcion','clave_prod_serv','clave_unidad',
        'cantidad','precio_unitario','descuento','impuesto_trasladado','impuesto_retenido','total'
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:4',
        'descuento' => 'decimal:2',
        'impuesto_trasladado' => 'decimal:2',
        'impuesto_retenido' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
