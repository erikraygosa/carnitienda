<?php
// app/Models/ArPayment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArPayment extends Model
{
    protected $table = 'ar_payments';

    protected $fillable = [
        'accounts_receivable_id', // FK al movimiento AR
        'fecha',
        'payment_type_id',
        'monto',                  // << en DB es "monto"
        'referencia',
        'driver_id',              // si aplica
        'recibido_por',           // si aplica (puedes guardar auth()->id())
        'nota',                   // << en DB es "nota", no "notes"
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function accountReceivable() {
        return $this->belongsTo(\App\Models\AccountReceivable::class, 'accounts_receivable_id');
    }
}
