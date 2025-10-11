<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceListItem extends Model
{
    protected $fillable = ['price_list_id','product_id','precio'];
    protected $casts = ['precio' => 'decimal:4'];

    public function priceList() { return $this->belongsTo(PriceList::class); }
    public function product()   { return $this->belongsTo(Product::class); }
}
