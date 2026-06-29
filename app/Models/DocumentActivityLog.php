<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentActivityLog extends Model
{
    protected $fillable = ['document_type','document_id','action','old_status','new_status','user_id','nota','changes'];

    protected $casts = ['changes' => 'array'];

    public function document()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
