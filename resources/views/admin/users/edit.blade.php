<x-admin-layout
    title="Editar usuario"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Usuarios','url'=>route('admin.users.index')],
        ['name'=>$user->name],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.users.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Volver
        </a>
        <button form="user-form"
                class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Guardar cambios
        </button>
    </x-slot>

    <x-wire-card>
        <form id="user-form" action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-5">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Nueva contraseña
                        <span class="text-gray-400 font-normal text-xs">(dejar vacío para no cambiar)</span>
                    </label>
                    <input type="password" name="password"
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation"
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Almacén
                        <span class="text-gray-400 font-normal text-xs">(requerido para rol POS)</span>
                    </label>
                    <select name="warehouse_id"
                            class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- sin almacén --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}"
                                {{ old('warehouse_id', $user->warehouse_id) == $w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Roles --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Roles <span class="text-red-500">*</span>
                    <span class="text-gray-400 font-normal text-xs ml-1">(selecciona uno o más)</span>
                </label>
                @error('roles')<p class="mb-2 text-xs text-red-600">{{ $message }}</p>@enderror
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    @foreach($roles as $role)
                    @php
                        $checked = in_array($role->name, old('roles', $user->roles->pluck('name')->toArray()));
                        $colorClass = match($role->name) {
                            'admin'     => 'border-indigo-300 bg-indigo-50 text-indigo-700',
                            'ventas'    => 'border-emerald-300 bg-emerald-50 text-emerald-700',
                            'logistica' => 'border-amber-300 bg-amber-50 text-amber-700',
                            'cxc'       => 'border-blue-300 bg-blue-50 text-blue-700',
                            'pos'       => 'border-violet-300 bg-violet-50 text-violet-700',
                            'cajero'    => 'border-rose-300 bg-rose-50 text-rose-700',
                            default     => 'border-gray-300 bg-gray-50 text-gray-700',
                        };
                    @endphp
                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer hover:opacity-80 {{ $checked ? $colorClass : 'border-gray-200 bg-white text-gray-600' }}">
                        <input type="checkbox"
                               name="roles[]"
                               value="{{ $role->name }}"
                               {{ $checked ? 'checked' : '' }}
                               class="rounded border-gray-300 text-indigo-600">
                        <div>
                            <div class="text-sm font-medium">{{ ucfirst($role->name) }}</div>
                            <div class="text-xs opacity-70">
                                {{ $role->permissions->count() }} permiso(s)
                            </div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Permisos por rol --}}
            <div class="border rounded-lg overflow-hidden">
                <div class="px-4 py-2 bg-gray-50 border-b text-sm font-medium text-gray-700">
                    Permisos incluidos por rol seleccionado
                </div>
                <div class="p-4">
                    @foreach($roles as $role)
                    <div class="role-perms mb-3" data-role="{{ $role->name }}" style="{{ in_array($role->name, $user->roles->pluck('name')->toArray()) ? '' : 'display:none' }}">
                        <div class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ ucfirst($role->name) }}</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($role->permissions as $perm)
                                <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">
                                    {{ $perm->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                    <p id="no-roles-msg" class="text-xs text-gray-400 {{ count($user->roles) > 0 ? 'hidden' : '' }}">
                        Selecciona un rol para ver sus permisos.
                    </p>
                </div>
            </div>

            <div class="pt-3 border-t text-xs text-gray-400">
                Creado: {{ $user->created_at->format('d/m/Y H:i') }}
                · Actualizado: {{ $user->updated_at->format('d/m/Y H:i') }}
            </div>
        </form>
    </x-wire-card>

    <script>
    (function() {
        var checkboxes = document.querySelectorAll('input[name="roles[]"]');
        var noMsg      = document.getElementById('no-roles-msg');

        function updatePerms() {
            var anyChecked = false;
            checkboxes.forEach(function(chk) {
                var panel = document.querySelector('.role-perms[data-role="' + chk.value + '"]');
                if (panel) panel.style.display = chk.checked ? '' : 'none';
                if (chk.checked) anyChecked = true;
            });
            if (noMsg) noMsg.classList.toggle('hidden', anyChecked);
        }

        checkboxes.forEach(function(chk) {
            chk.addEventListener('change', updatePerms);
        });
    })();
    </script>

</x-admin-layout>