<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchArAssignment extends Model
{
    protected $fillable = [
        'dispatch_id',
        'client_id',
        'saldo_asignado',
        'monto_cobrado',
        'status',
    ];

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}