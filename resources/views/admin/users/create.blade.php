<x-admin-layout
    title="Nuevo usuario"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Usuarios','url'=>route('admin.users.index')],
        ['name'=>'Nuevo'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.users.index') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white hover:bg-gray-50">
            Volver
        </a>
        <button form="user-form"
                class="ml-2 inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Guardar
        </button>
    </x-slot>

    @php
        $loginMode      = \App\Models\SystemSetting::get('auth.login_mode', 'email');
        $usernameDomain = \App\Models\SystemSetting::get('auth.username_domain', '');
        $isUsername     = $loginMode === 'username' && $usernameDomain !== '';
    @endphp

    <x-wire-card>
        <form id="user-form" action="{{ route('admin.users.store') }}" method="POST" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    @if ($isUsername)
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Usuario <span class="text-red-500">*</span>
                            <span class="font-normal text-gray-400 text-xs">— se guardará como <em>usuario@{{ $usernameDomain }}</em></span>
                        </label>
                        <div class="flex items-center rounded-md border border-gray-300 overflow-hidden focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500">
                            <input type="text" name="email" id="email-input"
                                   value="{{ old('email') ? Str::before(old('email'), '@') : '' }}"
                                   required placeholder="nombre.usuario"
                                   class="flex-1 min-w-0 px-3 py-2 text-sm text-gray-800 border-0 focus:outline-none focus:ring-0">
                            <span class="pr-3 text-gray-400 text-sm whitespace-nowrap select-none bg-white">@{{ $usernameDomain }}</span>
                        </div>
                    @else
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @endif
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña <span class="text-red-500">*</span></label>
                    <input type="password" name="password" required
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña <span class="text-red-500">*</span></label>
                    <input type="password" name="password_confirmation" required
                           class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rol <span class="text-red-500">*</span></label>
                    <select name="role" required
                            class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                            id="role-select">
                        <option value="">-- seleccionar --</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                                {{ ucfirst($role->name) }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div id="warehouse-wrap">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Almacén
                        <span class="text-gray-400 font-normal text-xs">(requerido para rol POS)</span>
                    </label>
                    <select name="warehouse_id"
                            class="w-full rounded-md border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- sin almacén --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ old('warehouse_id') == $w->id ? 'selected' : '' }}>
                                {{ $w->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            @if (auth()->user()->is_superadmin)
            <div class="pt-2 border-t border-gray-100">
                <label class="flex items-center gap-3 cursor-pointer select-none w-fit">
                    <input type="checkbox" name="is_superadmin" value="1"
                           {{ old('is_superadmin') ? 'checked' : '' }}
                           class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                    <span class="text-sm font-medium text-gray-700">
                        Superadministrador
                        <span class="font-normal text-gray-400 text-xs">— acceso total al sistema, incluido panel de configuración</span>
                    </span>
                </label>
            </div>
            @endif
        </form>
    </x-wire-card>

    <script>
    (function() {
        var roleSelect     = document.getElementById('role-select');
        var warehouseWrap  = document.getElementById('warehouse-wrap');
        var warehouseSelect = warehouseWrap.querySelector('select');

        function toggleWarehouse() {
            var isPOS = roleSelect.value === 'pos';
            warehouseWrap.style.display = isPOS ? '' : '';
            warehouseSelect.required    = isPOS;
        }

        roleSelect.addEventListener('change', toggleWarehouse);
        toggleWarehouse();

        @if ($isUsername)
        var emailInput = document.getElementById('email-input');
        var form       = document.getElementById('user-form');
        var domain     = '{{ $usernameDomain }}';

        form.addEventListener('submit', function () {
            var val = emailInput.value.trim();
            if (val && !val.includes('@')) {
                emailInput.value = val + '@' + domain;
            }
        });
        @endif
    })();
    </script>
</x-admin-layout>