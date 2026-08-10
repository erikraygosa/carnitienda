<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'editar precio en pos']);

        // Mismo comportamiento de hoy (solo rol admin) — a partir de aquí ya
        // se puede otorgar a otros roles desde el panel de Roles.
        Role::where('name', 'admin')->first()?->givePermissionTo('editar precio en pos');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'editar precio en pos')->delete();
    }
};
