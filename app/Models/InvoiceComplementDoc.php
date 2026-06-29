<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceComplementDoc extends Model
{
    protected $fillable = [
        'invoice_id',
        'related_invoice_id',
        'num_parcialidad',
        'imp_saldo_anterior',
        'imp_pagado',
        'imp_saldo_insoluto',
    ];

    protected $casts = [
        'imp_saldo_anterior' => 'decimal:2',
        'imp_pagado'         => 'decimal:2',
        'imp_saldo_insoluto' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id');
    }
}
