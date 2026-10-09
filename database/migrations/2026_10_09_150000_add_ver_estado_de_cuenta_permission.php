<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Acceso solo a Cobranza general / Estado de cuenta (sin abrir el resto
        // de Cuentas por cobrar), pensado para ventas.
        Permission::firstOrCreate(['name' => 'ver estado de cuenta']);

        foreach (['admin', 'ventas', 'cxc'] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo('ver estado de cuenta');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'ver estado de cuenta')->delete();
    }
};
