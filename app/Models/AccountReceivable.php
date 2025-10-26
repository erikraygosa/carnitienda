<?php
// app/Models/AccountReceivable.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AccountReceivable extends Model
{
    protected $table = 'accounts_receivable';

    protected $fillable = [
        'client_id', 'fecha', 'tipo', 'monto', 'descripcion',
        'source_type','source_id','created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function client() { return $this->belongsTo(Client::class); }

    public function source(): MorphTo { return $this->morphTo(); }
}
