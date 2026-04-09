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
                    <div class="flex items-center justify-between px-3 py-2 rounded-lg border {{ $colorClass }}">
                        <div>
                            <div class="font-medium text-sm">{{ ucfirst($role->name) }}</div>
                            <div class="text-xs opacity-70">{{ $role->permissions->count() }} permiso(s)</div>
                        </div>
                        <div class="flex gap-1">
                            <button type="button"
                                    data-role="{{ $role->name }}"
                                    data-role-id="{{ $role->id }}"
                                    class="btn-edit-role px-2 py-1 text-xs rounded border border-current hover:opacity-70">
                                Editar
                            </button>
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
                    <span id="role-title" class="px-2 py-0.5 rounded-full text-sm font-medium bg-indigo-100 text-indigo-700">
                        — selecciona un rol —
                    </span>
                </div>

                <div id="role-editor" class="hidden">
                    <form id="role-perm-form" method="POST" class="space-y-4">
                        @csrf @method('PUT')

                        @foreach($permissions as $modulo => $perms)
                        <div class="border rounded-lg overflow-hidden">
                            <div class="px-3 py-2 bg-gray-50 border-b flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600 uppercase">{{ $modulo }}</span>
                                <button type="button"
                                        class="btn-check-all text-xs text-indigo-600 hover:underline"
                                        data-modulo="{{ $modulo }}">
                                    Seleccionar todos
                                </button>
                            </div>
                            <div class="p-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($perms as $perm)
                                <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 rounded px-1 py-0.5 perm-label" data-modulo="{{ $modulo }}">
                                    <input type="checkbox"
                                           name="permissions[]"
                                           value="{{ $perm->name }}"
                                           class="perm-chk rounded border-gray-300 text-indigo-600">
                                    <span class="text-gray-700">{{ $perm->name }}</span>
                                    <form action="{{ route('admin.roles.permissions.destroy', $perm) }}" method="POST"
                                          class="ml-auto"
                                          onsubmit="return confirm('¿Eliminar permiso?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs text-red-400 hover:text-red-600">✕</button>
                                    </form>
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
                </div>

                <div id="role-empty" class="py-12 text-center text-gray-400 text-sm">
                    Selecciona un rol de la lista para editar sus permisos.
                </div>
            </x-wire-card>
        </div>
    </div>

<script>
(function() {
    var rolesData = {!! json_encode($rolesJson) !!};

    var editor    = document.getElementById('role-editor');
    var empty     = document.getElementById('role-empty');
    var title     = document.getElementById('role-title');
    var form      = document.getElementById('role-perm-form');

    // Botones editar rol
    document.querySelectorAll('.btn-edit-role').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var roleName = this.dataset.role;
            var roleId   = this.dataset.roleId;
            var role     = rolesData.find(function(r) { return r.name === roleName; });
            if (!role) return;

            // Actualizar título
            title.textContent = roleName.charAt(0).toUpperCase() + roleName.slice(1);

            // Actualizar action del form
            form.action = '/admin/roles/' + roleId;

            // Marcar permisos
            document.querySelectorAll('.perm-chk').forEach(function(chk) {
                chk.checked = role.permissions.indexOf(chk.value) !== -1;
            });

            editor.classList.remove('hidden');
            empty.classList.add('hidden');
        });
    });

    // Seleccionar todos por módulo
    document.querySelectorAll('.btn-check-all').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var modulo = this.dataset.modulo;
            var labels = document.querySelectorAll('.perm-label[data-modulo="' + modulo + '"]');
            var chks   = [];
            labels.forEach(function(l) {
                var c = l.querySelector('.perm-chk');
                if (c) chks.push(c);
            });

            var allChecked = chks.every(function(c) { return c.checked; });
            chks.forEach(function(c) { c.checked = !allChecked; });
            this.textContent = allChecked ? 'Seleccionar todos' : 'Deseleccionar todos';
        });
    });
})();
</script>

</x-admin-layout>