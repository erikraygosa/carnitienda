<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        User::whereIn('email', ['ventas@pavoreal.com', 'ventas1@pavoreal.com'])
            ->get()
            ->each(fn (User $u) => $u->givePermissionTo('reabrir despachos'));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        User::whereIn('email', ['ventas@pavoreal.com', 'ventas1@pavoreal.com'])
            ->get()
            ->each(fn (User $u) => $u->revokePermissionTo('reabrir despachos'));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
