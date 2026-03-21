<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchTransferAssignment extends Model
{
    protected $fillable = ['dispatch_id', 'stock_transfer_id', 'status'];

    public function dispatch()      { return $this->belongsTo(Dispatch::class); }
    public function stockTransfer() { return $this->belongsTo(StockTransfer::class); }
}