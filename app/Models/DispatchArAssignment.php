<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchArAssignment extends Model
{
    protected $fillable = [
        'dispatch_id',
        'client_id',
        'saldo_asignado',
        'monto_cobrado',
        'status',
    ];

    public function dispatch() { return $this->belongsTo(Dispatch::class); }
    public function client()   { return $this->belongsTo(Client::class); }
}