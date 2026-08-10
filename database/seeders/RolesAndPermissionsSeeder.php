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

            // Categorías
            'gestionar categorias',

            // Clientes
            'ver clientes', 'crear clientes', 'editar clientes', 'eliminar clientes',

            // Proveedores
            'ver proveedores', 'crear proveedores', 'editar proveedores', 'eliminar proveedores',

            // Almacenes
            'gestionar almacenes',

            // Órdenes de compra / Compras
            'ver ordenes de compra', 'crear ordenes de compra', 'editar ordenes de compra', 'cancelar ordenes de compra',
            'ver compras', 'recibir compras',

            // Stock / Inventario
            'ver stock', 'gestionar traspasos',

            // Precios por almacén
            'ver precios almacen', 'editar precios almacen', 'aplicar precios matriz',

            // Cotizaciones
            'ver cotizaciones', 'crear cotizaciones', 'editar cotizaciones', 'aprobar cotizaciones',

            // Pedidos
            'ver pedidos', 'crear pedidos', 'editar pedidos', 'cancelar pedidos', 'procesar pedidos',

            // Despachos / Logística
            'ver despachos', 'crear despachos', 'editar despachos', 'cerrar despachos',
            'salida de producto',

            // Facturas
            'ver facturas', 'crear facturas', 'timbrar facturas', 'cancelar facturas',
            'generar complementos pago', 'ver notas credito', 'crear notas credito',

            // CxC
            'ver cxc', 'registrar cobros', 'ver reportes cxc',

            // POS
            'usar pos',

            // Cajas
            'ver cajas', 'abrir cajas', 'cerrar cajas',

            // Caja de repartidores / liquidaciones
            'gestionar liquidaciones repartidores',

            // Reportes
            'ver reportes',
            'ver reporte notas de venta',
            'ver reporte ventas por producto',
            'ver reporte liquidaciones',

            // Auditoría
            'ver auditoria',

            // Configuración
            'ver configuracion', 'editar configuracion', 'gestionar roles',
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
            'ver productos',
            'ver clientes', 'crear clientes', 'editar clientes',
            'ver cotizaciones', 'crear cotizaciones', 'editar cotizaciones', 'aprobar cotizaciones',
            'ver pedidos', 'crear pedidos', 'editar pedidos',
            'procesar pedidos',
            'ver facturas', 'crear facturas',
            'usar pos',
            'ver stock',
            'ver reporte notas de venta',
            'ver reporte ventas por producto',
        ]);

        $logistica = Role::firstOrCreate(['name' => 'logistica']);
        $logistica->syncPermissions([
            'ver dashboard',
            'gestionar almacenes',
            'ver ordenes de compra', 'crear ordenes de compra', 'editar ordenes de compra',
            'ver compras', 'recibir compras',
            'ver stock', 'gestionar traspasos',
            'ver pedidos',
            'procesar pedidos',
            'salida de producto',
            'ver despachos', 'crear despachos', 'editar despachos', 'cerrar despachos',
            'ver reporte liquidaciones',
            'gestionar liquidaciones repartidores',
        ]);

        $cxc = Role::firstOrCreate(['name' => 'cxc']);
        $cxc->syncPermissions([
            'ver dashboard',
            'ver clientes',
            'ver pedidos',
            'ver facturas',
            'ver cxc', 'registrar cobros', 'ver reportes cxc',
            'generar complementos pago', 'ver notas credito', 'crear notas credito',
            'ver reportes',
            'ver reporte notas de venta',
            'ver reporte ventas por producto',
            'ver reporte liquidaciones',
            'gestionar liquidaciones repartidores',
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
            'ver reporte notas de venta',
        ]);
    }
}
