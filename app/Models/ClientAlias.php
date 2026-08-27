<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAlias extends Model
{
    protected $fillable = ['client_id', 'alias'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
