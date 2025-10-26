<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountsReceivable extends Model
{
    protected $table = 'accounts_receivable'; // ← MUY IMPORTANTE

    protected $fillable = [
        'client_id','tipo_doc','folio_documento','fecha','vencimiento','moneda',
        'subtotal','impuestos','total','saldo','status','warehouse_id','driver_id','created_by','sale_id'
    ];

    protected $casts = [
        'fecha' => 'date',
        'vencimiento' => 'date',
        'subtotal' => 'decimal:2',
        'impuestos' => 'decimal:2',
        'total' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    public function client() { return $this->belongsTo(Client::class); }
    public function sale()   { return $this->belongsTo(Sale::class); }
}
