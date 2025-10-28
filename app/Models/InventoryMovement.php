<?php

// app/Models/InventoryMovement.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model {
  protected $fillable=['warehouse_id','product_id','tipo','cantidad','motivo','source_type','source_id','created_by'];
  public function source(): MorphTo { return $this->morphTo(); }
  public function warehouse(){ return $this->belongsTo(Warehouse::class); }
  public function product(){ return $this->belongsTo(Product::class); }
}
