<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:gestionar roles'),
        ];
    }

    // Orden y etiquetas de los grupos en la UI
    protected const GRUPOS = [
        'dashboard'      => 'Dashboard',
        'usuarios'       => 'Usuarios',
        'productos'      => 'Productos & Catálogo',
        'clientes'       => 'Clientes',
        'proveedores'    => 'Proveedores',
        'compras'        => 'Compras',
        'inventario'     => 'Inventario & Stock',
        'ventas'         => 'Ventas & Pedidos',
        'despachos'      => 'Logística & Despacho',
        'facturas'       => 'Facturación',
        'cxc'            => 'Cuentas por Cobrar',
        'pos'            => 'Punto de Venta',
        'cajas'          => 'Cajas',
        'reportes'       => 'Reportes',
        'auditoria'      => 'Auditoría',
        'configuracion'  => 'Configuración',
        'otros'          => 'Otros',
    ];

    protected function grupoDePermiso(string $name): string
    {
        // Reportes (cualquier variante)
        if ($name === 'ver reportes' || str_starts_with($name, 'ver reporte ') || str_starts_with($name, 'ver reportes')) {
            return 'reportes';
        }
        // CxC
        if (in_array($name, ['ver cxc', 'registrar cobros', 'ver reportes cxc', 'crear facturas'])) {
            return 'cxc';
        }
        // Inventario
        if (in_array($name, ['ver stock', 'gestionar traspasos', 'gestionar almacenes']) ||
            str_contains($name, 'precios almacen') || str_contains($name, 'precios matriz')) {
            return 'inventario';
        }
        // Logística / Despacho
        if (str_starts_with($name, 'ver despacho') || str_starts_with($name, 'crear despacho') ||
            str_starts_with($name, 'editar despacho') || str_starts_with($name, 'cerrar despacho') ||
            $name === 'salida de producto') {
            return 'despachos';
        }
        // Ventas & Pedidos
        if (in_array($name, ['procesar pedidos', 'cancelar pedidos']) ||
            str_starts_with($name, 'ver pedido') || str_starts_with($name, 'crear pedido') ||
            str_starts_with($name, 'editar pedido') || str_starts_with($name, 'aprobar ') ||
            str_starts_with($name, 'ver cotizaci') || str_starts_with($name, 'crear cotizaci') ||
            str_starts_with($name, 'editar cotizaci')) {
            return 'ventas';
        }
        // Compras
        if (str_starts_with($name, 'ver orden') || str_starts_with($name, 'crear orden') ||
            str_starts_with($name, 'editar orden') || str_starts_with($name, 'cancelar orden') ||
            str_starts_with($name, 'ver compra') || $name === 'recibir compras') {
            return 'compras';
        }
        // Productos
        if (str_starts_with($name, 'gestionar categ') || str_starts_with($name, 'ver producto') ||
            str_starts_with($name, 'crear producto') || str_starts_with($name, 'editar producto') ||
            str_starts_with($name, 'eliminar producto')) {
            return 'productos';
        }
        // Facturas (incluye complementos de pago y notas de crédito, que
        // son CFDI emitidos desde el mismo módulo)
        if (str_starts_with($name, 'ver factura') || str_starts_with($name, 'timbrar') ||
            str_starts_with($name, 'cancelar factura') ||
            str_contains($name, 'complementos pago') || str_contains($name, 'notas credito')) {
            return 'facturas';
        }
        // Configuración (gestionar roles no cae por segunda palabra)
        if ($name === 'gestionar roles') return 'configuracion';
        // Default: segunda palabra del nombre
        $segunda = explode(' ', $name)[1] ?? 'otros';
        return array_key_exists($segunda, self::GRUPOS) ? $segunda : 'otros';
    }

    public function index(Request $request)
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        $allPerms = Permission::orderBy('name')->get();

        // Agrupar con lógica semántica
        $grouped = $allPerms->groupBy(fn($p) => $this->grupoDePermiso($p->name));

        // Ordenar grupos según el orden definido en GRUPOS
        $grupoOrder = array_keys(self::GRUPOS);
        $permissions = collect(self::GRUPOS)
            ->keys()
            ->mapWithKeys(fn($key) => [$key => $grouped->get($key, collect())])
            ->filter(fn($g) => $g->isNotEmpty());

        $selectedRole = $request->filled('role')
            ? $roles->firstWhere('id', (int) $request->input('role'))
            : null;

        $grupoLabels = self::GRUPOS;

        return view('admin.roles.index', compact('roles', 'permissions', 'selectedRole', 'grupoLabels'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
        ]);

        Role::create(['name' => $data['name']]);

        session()->flash('swal', ['icon' => 'success', 'title' => 'Rol creado']);
        return redirect()->route('admin.roles.index');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        session()->flash('swal', ['icon' => 'success', 'title' => 'Permisos actualizados']);
        return redirect()->route('admin.roles.index');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, ['admin', 'superadmin'])) {
            session()->flash('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'No puedes eliminar este rol.']);
            return back();
        }

        $role->delete();
        session()->flash('swal', ['icon' => 'success', 'title' => 'Rol eliminado']);
        return redirect()->route('admin.roles.index');
    }

    public function storePermission(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
        ]);

        Permission::create(['name' => $data['name']]);

        session()->flash('swal', ['icon' => 'success', 'title' => 'Permiso creado']);
        return redirect()->route('admin.roles.index');
    }

    public function destroyPermission(Permission $permission)
    {
        $permission->delete();
        session()->flash('swal', ['icon' => 'success', 'title' => 'Permiso eliminado']);
        return redirect()->route('admin.roles.index');
    }
}