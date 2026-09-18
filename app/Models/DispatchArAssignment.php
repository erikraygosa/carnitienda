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

    // Notas/pedidos específicos que se marcaron para esta asignación — antes
    // no se guardaban y cualquier pantalla que necesitara "las notas de este
    // despacho" volvía a traer TODAS las notas pendientes del cliente,
    // ignorando cuáles se habían seleccionado realmente.
    public function orders()
    {
        return $this->belongsToMany(SalesOrder::class, 'dispatch_ar_assignment_orders', 'dispatch_ar_assignment_id', 'sales_order_id');
    }
}