<?php

// app/Models/CashRegister.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model {
  protected $fillable=['warehouse_id','user_id','fecha','monto_apertura','ingresos','egresos','ventas_efectivo','monto_cierre','estatus','opened_at','closed_at','closed_by','notas'];
  protected $casts=['fecha'=>'date','opened_at'=>'datetime','closed_at'=>'datetime'];
  public function warehouse(){ return $this->belongsTo(Warehouse::class); }
  public function user(){ return $this->belongsTo(User::class); }
  public function movements(){ return $this->hasMany(CashMovement::class); }
  public function posSales(){ return $this->hasMany(PosSale::class); }
}
