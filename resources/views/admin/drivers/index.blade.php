<x-admin-layout
    title="Choferes"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('admin.dashboard')],
        ['name' => 'Choferes'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.drivers.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            + Nuevo chofer
        </a>
    </x-slot>

    <x-wire-card>
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Nombre</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Teléfono</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Licencia</th>
                    <th class="px-4 py-2 text-center font-medium text-gray-500">Despachos</th>
                    <th class="px-4 py-2 text-center font-medium text-gray-500">Estado</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($drivers as $driver)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-medium text-gray-800">{{ $driver->nombre }}</td>
                    <td class="px-4 py-2 text-gray-500">{{ $driver->telefono ?? '—' }}</td>
                    <td class="px-4 py-2 text-gray-500 font-mono text-xs">{{ $driver->licencia ?? '—' }}</td>
                    <td class="px-4 py-2 text-center text-gray-600">{{ $driver->dispatches_count }}</td>
                    <td class="px-4 py-2 text-center">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            {{ $driver->activo ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $driver->activo ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right space-x-2">
                        <a href="{{ route('admin.drivers.edit', $driver) }}"
                           class="text-indigo-600 hover:underline text-xs">Editar</a>
                        <form method="POST" action="{{ route('admin.drivers.destroy', $driver) }}"
                              class="inline"
                              onsubmit="return confirm('¿Eliminar a {{ addslashes($driver->nombre) }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-500 hover:underline text-xs">Eliminar</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400">No hay choferes registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </x-wire-card>
</x-admin-layout>
