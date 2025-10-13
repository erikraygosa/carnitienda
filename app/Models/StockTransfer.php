<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
      protected $fillable = [
        'from_warehouse_id','to_warehouse_id','user_id','fecha','status','notas',
    ];
    protected $casts = ['fecha' => 'date'];

    public function items()          { return $this->hasMany(StockTransferItem::class); }
    public function fromWarehouse()  { return $this->belongsTo(Warehouse::class,'from_warehouse_id'); }
    public function toWarehouse()    { return $this->belongsTo(Warehouse::class,'to_warehouse_id'); }
    public function user()           { return $this->belongsTo(User::class); }
}
