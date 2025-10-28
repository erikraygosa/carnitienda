<?php

// app/Models/PosSale.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PosSale extends Model {
  protected $fillable=['cash_register_id','warehouse_id','user_id','client_id','fecha','subtotal','descuento','impuestos','total','metodo_pago','efectivo','cambio','referencia'];
  protected $casts=['fecha'=>'datetime'];
  public function items(){ return $this->hasMany(PosSaleItem::class); }
  public function warehouse(){ return $this->belongsTo(Warehouse::class); }
  public function user(){ return $this->belongsTo(User::class); }
  public function client(){ return $this->belongsTo(Client::class); }
}
