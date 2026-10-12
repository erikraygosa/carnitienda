<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /** Permisos de surtido: solo el rol "logistica" los conserva. */
    private array $surtido = [
        'salida de producto', 'procesar pedidos',
        'crear pedidos desde surtido', 'editar precio en surtido',
    ];

    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Rol "despachos": lo mismo que logistica, pero sin surtido (dar salida
        // de producto). Para quien arma despachos pero no surte.
        $logistica = Role::where('name', 'logistica')->first();
        if (! $logistica) return;

        $despachos = Role::firstOrCreate(['name' => 'despachos', 'guard_name' => $logistica->guard_name]);
        $permisos  = $logistica->permissions->pluck('name')->diff($this->surtido)->values()->all();
        $despachos->syncPermissions(Permission::whereIn('name', $permisos)->get());

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'despachos')->first()?->delete();
    }
};
