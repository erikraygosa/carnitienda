<?php

// app/Models/DriverCashMovement.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DriverCashMovement extends Model
{
    protected $fillable = [
        'register_id','driver_id','tipo','monto','descripcion','source_type','source_id','created_by'
    ];

    public function source(): MorphTo {
        return $this->morphTo();
    }

    public function register() {
        return $this->belongsTo(DriverCashRegister::class, 'register_id');
    }
}
