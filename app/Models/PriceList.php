<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    protected $fillable = ['nombre','moneda','activo'];
    protected $casts = ['activo' => 'boolean'];

    public function items() { return $this->hasMany(PriceListItem::class); }
}
