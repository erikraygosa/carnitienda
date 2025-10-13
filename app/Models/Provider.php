<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $fillable = [
        'nombre','rfc','email','telefono','contacto','direccion',
        'ciudad','estado','cp','activo','notas',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
