<?php

// app/Models/CashMovement.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMovement extends Model {
  protected $fillable=['cash_register_id','tipo','monto','concepto','source_type','source_id','created_by'];
  public function register(){ return $this->belongsTo(CashRegister::class,'cash_register_id'); }
  public function source(): MorphTo { return $this->morphTo(); }
}
