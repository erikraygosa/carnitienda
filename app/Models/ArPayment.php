<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArPayment extends Model
{
    protected $fillable = [
        'accounts_receivable_id','fecha','payment_type_id','monto','referencia','driver_id','recibido_por','nota'
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function ar() { return $this->belongsTo(AccountsReceivable::class, 'accounts_receivable_id'); }
}
