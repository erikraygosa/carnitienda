<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ['nombre','telefono','licencia','activo'];
    protected $casts = ['activo' => 'boolean'];

    public function dispatches()
    {
        return $this->hasMany(\App\Models\Dispatch::class, 'driver_id');
    }
}
