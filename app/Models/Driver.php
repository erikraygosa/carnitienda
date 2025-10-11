<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ['nombre','telefono','licencia','activo'];
    protected $casts = ['activo' => 'boolean'];
}
