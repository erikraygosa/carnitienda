<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
  public function index()
{
    $roles = Role::with('permissions')->orderBy('name')->get();
    $permissions = Permission::orderBy('name')->get()->groupBy(function($p) {
        return explode(' ', $p->name)[1] ?? 'general';
    });

    // Preparar datos para JS
    $rolesJson = $roles->map(function($r) {
        return [
            'id'          => $r->id,
            'name'        => $r->name,
            'permissions' => $r->permissions->pluck('name')->values(),
        ];
    });

    return view('admin.roles.index', compact('roles', 'permissions', 'rolesJson'));
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