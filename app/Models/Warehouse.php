<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['codigo','nombre','direccion','activo','is_primary'];
    protected $casts = ['activo' => 'boolean', 'is_primary' => 'boolean'];
}
