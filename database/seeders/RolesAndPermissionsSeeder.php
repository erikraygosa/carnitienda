<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permisos
        $permisos = [
            // Dashboard
            'ver dashboard',

            // Usuarios
            'ver usuarios', 'crear usuarios', 'editar usuarios', 'eliminar usuarios',

            // Productos
            'ver productos', 'crear productos', 'editar productos', 'eliminar productos',

            // Clientes
            'ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes',

            // Proveedores
            'ver proveedores', 'crear proveedores', 'editar proveedores', 'eliminar proveedores',

            // Pedidos
            'ver pedidos', 'crear pedidos', 'editar pedidos', 'cancelar pedidos',

            // Despachos / Logística
            'ver despachos', 'crear despachos', 'editar despachos', 'cerrar despachos',

            // CxC
            'ver cxc', 'registrar cobros', 'ver reportes cxc',

            // POS
            'usar pos',

            // Stock
            'ver stock', 'gestionar traspasos',

            // Cajas
            'ver cajas', 'abrir cajas', 'cerrar cajas',

            // Reportes
            'ver reportes',

            // Configuración
            'ver configuracion', 'editar configuracion',
        ];

        foreach ($permisos as $permiso) {
            Permission::firstOrCreate(['name' => $permiso]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        $ventas = Role::firstOrCreate(['name' => 'ventas']);
        $ventas->syncPermissions([
            'ver dashboard',
            'ver productos', 'ver clientes',
            'ver pedidos', 'crear pedidos', 'editar pedidos',
            'usar pos',
            'ver stock',
        ]);

        $logistica = Role::firstOrCreate(['name' => 'logistica']);
        $logistica->syncPermissions([
            'ver dashboard',
            'ver pedidos',
            'ver despachos', 'crear despachos', 'editar despachos', 'cerrar despachos',
            'ver stock', 'gestionar traspasos',
        ]);

        $cxc = Role::firstOrCreate(['name' => 'cxc']);
        $cxc->syncPermissions([
            'ver dashboard',
            'ver clientes',
            'ver pedidos',
            'ver cxc', 'registrar cobros', 'ver reportes cxc',
            'ver reportes',
        ]);

        $pos = Role::firstOrCreate(['name' => 'pos']);
        $pos->syncPermissions([
            'usar pos',
            'ver productos',
        ]);

        $cajero = Role::firstOrCreate(['name' => 'cajero']);
        $cajero->syncPermissions([
            'ver dashboard',
            'ver cajas', 'abrir cajas', 'cerrar cajas',
            'usar pos',
            'ver reportes',
        ]);
    }
}