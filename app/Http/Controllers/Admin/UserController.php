<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'warehouse'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles      = Role::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('nombre')->get();
        return view('admin.users.create', compact('roles', 'warehouses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8|confirmed',
            'role'         => 'required|exists:roles,name',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $user = User::create([
            'name'         => $data['name'],
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
            'warehouse_id' => $data['warehouse_id'] ?? null,
        ]);

        $user->assignRole($data['role']);

        session()->flash('swal', ['icon' => 'success', 'title' => 'Usuario creado', 'text' => 'El usuario fue creado correctamente.']);
        return redirect()->route('admin.users.index');
    }

    public function edit(User $user)
{
    $roles      = Role::with('permissions')->orderBy('name')->get();
    $warehouses = Warehouse::orderBy('nombre')->get();
    return view('admin.users.edit', compact('user', 'roles', 'warehouses'));
}
        public function update(Request $request, User $user)
{
    $data = $request->validate([
        'name'         => 'required|string|max:255',
        'email'        => 'required|email|unique:users,email,' . $user->id,
        'password'     => 'nullable|string|min:8|confirmed',
        'roles'        => 'required|array|min:1',
        'roles.*'      => 'exists:roles,name',
        'warehouse_id' => 'nullable|exists:warehouses,id',
    ]);

    $user->update([
        'name'         => $data['name'],
        'email'        => $data['email'],
        'warehouse_id' => $data['warehouse_id'] ?? null,
    ]);

    if (!empty($data['password'])) {
        $user->update(['password' => Hash::make($data['password'])]);
    }

    $user->syncRoles($data['roles']);

    session()->flash('swal', ['icon' => 'success', 'title' => 'Usuario actualizado', 'text' => 'Los cambios fueron guardados.']);
    return redirect()->route('admin.users.index');
}

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            session()->flash('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'No puedes eliminar tu propio usuario.']);
            return back();
        }

        $user->delete();
        session()->flash('swal', ['icon' => 'success', 'title' => 'Eliminado', 'text' => 'Usuario eliminado.']);
        return redirect()->route('admin.users.index');
    }
}