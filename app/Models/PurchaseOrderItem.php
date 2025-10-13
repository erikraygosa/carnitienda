<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id','product_id','qty_ordered','price','discount','tax_rate','total'
    ];

    public function order(){ return $this->belongsTo(PurchaseOrder::class,'purchase_order_id'); }
    public function product(){ return $this->belongsTo(Product::class); }
}
