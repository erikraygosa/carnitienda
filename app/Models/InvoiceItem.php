<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id','product_id','clave_prod_serv','clave_unidad','unidad','descripcion',
        'cantidad','valor_unitario','descuento',
        'objeto_imp','base','iva_pct','iva_importe','ieps_pct','ieps_importe','importe',
    ];

    protected $casts = [
        'cantidad'       => 'decimal:6',
        'valor_unitario' => 'decimal:6',
        'descuento'      => 'decimal:6',
        'base'           => 'decimal:6',
        'iva_pct'        => 'decimal:4',
        'iva_importe'    => 'decimal:6',
        'ieps_pct'       => 'decimal:4',
        'ieps_importe'   => 'decimal:6',
        'importe'        => 'decimal:6',
    ];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
