<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permisos = ['ver precios almacen', 'editar precios almacen', 'aplicar precios matriz'];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // Por ahora solo admin — precios es sensible, se abre a más roles si se pide.
        $admin = Role::where('name', 'admin')->first();
        $admin?->givePermissionTo($permisos);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['ver precios almacen', 'editar precios almacen', 'aplicar precios matriz'])->delete();
    }
};
