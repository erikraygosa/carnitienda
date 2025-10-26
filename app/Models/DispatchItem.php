<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchItem extends Model
{
    protected $fillable = [
        'dispatch_id','sales_order_id','referencia','volumen','peso','status',
    ];

    public function dispatch(){ return $this->belongsTo(Dispatch::class); }
    public function order()   { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
}
