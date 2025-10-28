<?php

// app/Models/PosSaleItem.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PosSaleItem extends Model {
  protected $fillable=['pos_sale_id','product_id','cantidad','precio_unitario','descuento','impuestos','importe'];
  public function sale(){ return $this->belongsTo(PosSale::class,'pos_sale_id'); }
  public function product(){ return $this->belongsTo(Product::class); }
}
