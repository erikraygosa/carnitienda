<?php
// app/Models/DriverCashRegister.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class DriverCashRegister extends Model
{
    protected $fillable = [
        'driver_id',
        'fecha',
        'saldo_inicial',
        'saldo_cargos',
        'saldo_abonos',
        'saldo_final',
        'estatus',
        'opened_at',
        'closed_at',
        'opened_by',
        'closed_by',
        'notas',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'opened_at'  => 'datetime',
        'closed_at'  => 'datetime',
    ];

    // Relación con el chofer
    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    // Relación con movimientos
    public function movements(): HasMany
    {
        return $this->hasMany(DriverCashMovement::class, 'register_id');
    }

    // saldo_actual = inicial + cargos - abonos
    protected function saldoActual(): Attribute
    {
        return Attribute::get(fn () =>
            ($this->saldo_inicial + $this->saldo_cargos) - $this->saldo_abonos
        );
    }
}
