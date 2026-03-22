<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check() || ! auth()->user()->is_superadmin) {
            abort(403, 'Acceso restringido a superadministradores.');
        }

        return $next($request);
    }
}