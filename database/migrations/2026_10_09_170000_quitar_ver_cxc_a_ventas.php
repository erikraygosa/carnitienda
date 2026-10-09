<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Ventas tenía "ver cxc" (todo Cuentas por cobrar y el reporte general de
        // cobranza). Solo contabilidad/admin deben ver todo; ventas queda con
        // "ver estado de cuenta" (un cliente a la vez).
        Role::where('name', 'ventas')->first()?->revokePermissionTo('ver cxc');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'ventas')->first()?->givePermissionTo('ver cxc');
    }
};
