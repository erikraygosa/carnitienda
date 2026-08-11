<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'ajustar inventario']);

        // Antes los ajustes de inventario estaban amarrados a 'gestionar
        // traspasos' (logistica lo tenía sin necesitarlo específicamente).
        // Por ahora solo admin — es una capacidad sensible (crea/destruye
        // stock de la nada, no mueve stock existente como un traspaso).
        Role::where('name', 'admin')->first()?->givePermissionTo('ajustar inventario');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'ajustar inventario')->delete();
    }
};
