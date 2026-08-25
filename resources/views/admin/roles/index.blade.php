<x-admin-layout
    title="Roles y permisos"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Roles y permisos'],
    ]"
>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- ══ COLUMNA IZQUIERDA: Roles ══ --}}
        <div class="lg:col-span-1 space-y-4">

            {{-- Crear rol --}}
            <x-wire-card>
                <h3 class="font-semibold text-gray-800 mb-3">Nuevo rol</h3>
                <form action="{{ route('admin.roles.store') }}" method="POST" class="flex gap-2">
                    @csrf
                    <input type="text" name="name" placeholder="Nombre del rol"
                           class="flex-1 rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <button type="submit"
                            class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                        + Crear
                    </button>
                </form>
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </x-wire-card>

            {{-- Lista de roles --}}
            <x-wire-card>
                <h3 class="font-semibold text-gray-800 mb-3">Roles</h3>
                <div class="space-y-2">
                    @foreach($roles as $role)
                    @php
                        $isSelected = $selectedRole && $selectedRole->id === $role->id;
                        $colorClass = match($role->name) {
                            'admin'     => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                            'ventas'    => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                            'logistica' => 'bg-amber-100 text-amber-700 border-amber-200',
                            'cxc'       => 'bg-blue-100 text-blue-700 border-blue-200',
                            'pos'       => 'bg-violet-100 text-violet-700 border-violet-200',
                            'cajero'    => 'bg-rose-100 text-rose-700 border-rose-200',
                            default     => 'bg-gray-100 text-gray-700 border-gray-200',
                        };
                    @endphp
                    <div class="flex items-center justify-between px-3 py-2 rounded-lg border {{ $colorClass }} {{ $isSelected ? 'ring-2 ring-indigo-400' : '' }}">
                        <div>
                            <div class="font-medium text-sm">{{ ucfirst($role->name) }}</div>
                            <div class="text-xs opacity-70">{{ $role->permissions->count() }} permiso(s)</div>
                        </div>
                        <div class="flex gap-1">
                            <a href="{{ route('admin.roles.index', ['role' => $role->id]) }}"
                               class="px-2 py-1 text-xs rounded border border-current hover:opacity-70">
                                Editar
                            </a>
                            @if(!in_array($role->name, ['admin','superadmin']))
                            <form action="{{ route('admin.roles.destroy', $role) }}" method="POST"
                                  onsubmit="return confirm('¿Eliminar rol {{ $role->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="px-2 py-1 text-xs rounded border border-red-300 text-red-600 hover:bg-red-50">
                                    ✕
                                </button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </x-wire-card>

            {{-- Nuevo permiso --}}
            <x-wire-card>
                <h3 class="font-semibold text-gray-800 mb-3">Nuevo permiso</h3>
                <form action="{{ route('admin.roles.permissions.store') }}" method="POST" class="space-y-2">
                    @csrf
                    <input type="text" name="name"
                           placeholder="ej: ver reportes"
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-xs text-gray-400">Formato: verbo + módulo (ej: "ver clientes", "crear pedidos")</p>
                    <button type="submit"
                            class="w-full px-3 py-1.5 text-sm rounded-md bg-gray-700 text-white hover:bg-gray-800">
                        + Crear permiso
                    </button>
                </form>
            </x-wire-card>
        </div>

        {{-- ══ COLUMNA DERECHA: Editor de permisos del rol ══ --}}
        <div class="lg:col-span-2">
            <x-wire-card>
                <div class="flex items-center gap-2 mb-4">
                    <h3 class="font-semibold text-gray-800">Permisos del rol:</h3>
                    <span class="px-2 py-0.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-700">
                        {{ $selectedRole ? ucfirst($selectedRole->name) : '— selecciona un rol —' }}
                    </span>
                </div>

                @if($selectedRole)
                    <form action="{{ route('admin.roles.update', $selectedRole) }}" method="POST" class="space-y-4">
                        @csrf @method('PUT')

                        @foreach($permissions as $modulo => $perms)
                        @php $modulePerms = $perms->pluck('name')->all(); @endphp
                        <div class="border rounded-lg overflow-hidden">
                            <div class="px-3 py-2 bg-gray-50 border-b flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600 uppercase">{{ $grupoLabels[$modulo] ?? $modulo }}</span>
                                <button type="button"
                                        onclick="toggleModule(this)"
                                        class="text-xs text-indigo-600 hover:underline">
                                    Seleccionar todos
                                </button>
                            </div>
                            <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($perms as $perm)
                                <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 rounded px-1 py-0.5">
                                    <input type="checkbox"
                                           name="permissions[]"
                                           value="{{ $perm->name }}"
                                           class="perm-chk rounded border-gray-300 text-indigo-600"
                                           {{ $selectedRole->permissions->contains('name', $perm->name) ? 'checked' : '' }}>
                                    <span class="text-gray-700">{{ $perm->name }}</span>
                                    {{-- No se puede anidar un <form> aquí dentro (ya estamos dentro
                                         del <form> grande de "Guardar permisos") — el HTML no permite
                                         forms anidados y el navegador cierra el form grande de golpe
                                         en el primer permiso, dejando fuera todo lo que sigue
                                         (incluido el botón de Guardar). Este botón usa form= para
                                         enviarse al form compartido que está fuera, más abajo. --}}
                                    <button type="submit"
                                            form="perm-delete-form"
                                            formaction="{{ route('admin.roles.permissions.destroy', $perm) }}"
                                            onclick="return confirm('¿Eliminar permiso?')"
                                            class="ml-auto text-xs text-red-400 hover:text-red-600">✕</button>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endforeach

                        <div class="flex justify-end pt-2">
                            <button type="submit"
                                    class="px-4 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700 font-medium">
                                Guardar permisos
                            </button>
                        </div>
                    </form>
                    {{-- Form compartido y vacío: los botones "✕" de arriba le apuntan
                         via form="perm-delete-form" + formaction, ya que no pueden traer
                         su propio <form> anidado dentro del form grande de permisos. --}}
                    <form id="perm-delete-form" method="POST" style="display:none">
                        @csrf @method('DELETE')
                        <input type="hidden" name="role" value="{{ $selectedRole->id }}">
                    </form>
                @else
                    <div class="py-12 text-center text-gray-400 text-sm">
                        Selecciona un rol de la lista para editar sus permisos.
                    </div>
                @endif
            </x-wire-card>
        </div>
    </div>

    <script>
    function toggleModule(btn) {
        var section = btn.closest('.border.rounded-lg');
        var chks = section.querySelectorAll('.perm-chk');
        var allChecked = true;
        chks.forEach(function(c) { if (!c.checked) allChecked = false; });
        chks.forEach(function(c) { c.checked = !allChecked; });
        btn.textContent = allChecked ? 'Seleccionar todos' : 'Deseleccionar todos';
    }
    </script>

</x-admin-layout>
