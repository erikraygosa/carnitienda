<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Services\DocumentLogService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UserController extends Controller implements HasMiddleware
{
    public function __construct(private DocumentLogService $log) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ver usuarios', only: ['index']),
            new Middleware('can:crear usuarios', only: ['create', 'store']),
            new Middleware('can:editar usuarios', only: ['edit', 'update']),
            new Middleware('can:eliminar usuarios', only: ['destroy']),
        ];
    }

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
        // En modo usuario, el campo email llega como "juan" o "juan@empresa.com"
        if (SystemSetting::get('auth.login_mode', 'email') === 'username') {
            $domain = SystemSetting::get('auth.username_domain', '');
            $email  = $request->input('email', '');
            if ($domain !== '' && ! str_contains($email, '@')) {
                $request->merge(['email' => $email . '@' . ltrim($domain, '@')]);
            }
        }

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8|confirmed',
            'role'          => 'required|exists:roles,name',
            'warehouse_id'  => 'nullable|exists:warehouses,id',
            'is_superadmin' => 'nullable|boolean',
        ]);

        $isSuperadmin = auth()->user()->is_superadmin && ! empty($data['is_superadmin']);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => Hash::make($data['password']),
            'warehouse_id'  => $data['warehouse_id'] ?? null,
            'is_superadmin' => $isSuperadmin,
        ]);

        $user->assignRole($data['role']);

        $this->log->log($user, 'CREADO', null, null, null, 'Rol: ' . $data['role']);
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

    $this->log->log($user, 'EDITADO', null, null, null, 'Roles: ' . implode(', ', $data['roles']));
    session()->flash('swal', ['icon' => 'success', 'title' => 'Usuario actualizado', 'text' => 'Los cambios fueron guardados.']);
    return redirect()->route('admin.users.index');
}

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            session()->flash('swal', ['icon' => 'error', 'title' => 'Error', 'text' => 'No puedes eliminar tu propio usuario.']);
            return back();
        }

        $this->log->log($user, 'ELIMINADO', null, null, null, 'Usuario: ' . $user->email);
        $user->delete();
        session()->flash('swal', ['icon' => 'success', 'title' => 'Eliminado', 'text' => 'Usuario eliminado.']);
        return redirect()->route('admin.users.index');
    }
}