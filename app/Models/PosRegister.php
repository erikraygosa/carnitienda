<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosRegister extends Model
{
    protected $fillable = ['warehouse_id','nombre','serie_ticket','folio_actual','activo'];
    protected $casts = ['folio_actual' => 'integer', 'activo' => 'boolean'];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
