<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentActivityLog extends Model
{
    protected $fillable = ['document_type','document_id','action','old_status','new_status','user_id','nota'];

    public function document()
    {
        return $this->morphTo();
    }
}
