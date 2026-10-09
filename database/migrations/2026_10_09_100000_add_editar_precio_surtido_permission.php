<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'editar precio en surtido']);

        // Logística lo usa en el Panel de Surtido (agregar un producto al
        // pedido y, si hace falta, ajustar su precio).
        foreach (['admin', 'logistica'] as $rol) {
            Role::where('name', $rol)->first()?->givePermissionTo('editar precio en surtido');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'editar precio en surtido')->delete();
    }
};
