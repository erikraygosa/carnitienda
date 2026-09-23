<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'reabrir despachos']);

        Role::where('name', 'admin')->first()?->givePermissionTo('reabrir despachos');

        // Permiso sensible (reabre un despacho ya CERRADO/liquidado para
        // poder agregarle más pedidos) — se otorga a personas puntuales, no
        // a un rol completo (ventas/logística), para no dárselo de golpe a
        // todos los que tengan ese rol.
        User::whereIn('email', ['logistica2@pavoreal.com', 'contabilidad@pavoreal.com'])
            ->get()
            ->each(fn (User $u) => $u->givePermissionTo('reabrir despachos'));

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'reabrir despachos')->delete();
    }
};
