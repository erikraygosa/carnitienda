<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['codigo','nombre','direccion','activo'];
    protected $casts = ['activo' => 'boolean'];
}
