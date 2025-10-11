<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    protected $fillable = ['clave','descripcion','activo'];
    protected $casts = ['activo' => 'boolean'];
}
