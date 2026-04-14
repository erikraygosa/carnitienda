<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArPaymentItem extends Model
{
    protected $fillable = [
        'ar_payment_id',
        'sales_order_id',
        'monto_aplicado',
    ];

    protected $casts = [
        'monto_aplicado' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(ArPayment::class, 'ar_payment_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }
}