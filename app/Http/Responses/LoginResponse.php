<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = auth()->user();

        $redirect = match(true) {
            $user->hasRole('pos')       => route('admin.pos.create'),
            $user->hasRole('logistica') => route('admin.dispatches.index'),
            $user->hasRole('ventas')    => route('admin.sales-orders.index'),
            $user->hasRole('cxc')       => route('admin.ar.index'),
            $user->hasRole('cajero')    => route('admin.cash.index'),
            default                     => route('admin.dashboard'),
        };

        return redirect($redirect);
    }
}