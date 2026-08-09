<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Permisos dedicados para Complementos de Pago y Notas de Crédito,
     * antes cubiertos genéricamente por 'crear facturas'. Se crean y se
     * conceden a los roles que ya tenían acceso equivalente, para no
     * romper accesos existentes.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $nuevos = [
            'generar complementos pago',
            'ver notas credito',
            'crear notas credito',
        ];

        foreach ($nuevos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // admin: todo
        if ($admin = Role::where('name', 'admin')->first()) {
            $admin->givePermissionTo($nuevos);
        }

        // cxc: maneja cobranza, complementos y ajustes por nota de crédito
        if ($cxc = Role::where('name', 'cxc')->first()) {
            $cxc->givePermissionTo($nuevos);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'generar complementos pago',
            'ver notas credito',
            'crear notas credito',
        ])->delete();
    }
};
