<x-admin-layout
    title="Usuarios"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Usuarios'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.users.create') }}"
           class="inline-flex items-center px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            + Nuevo usuario
        </a>
    </x-slot>

    <x-wire-card>
        <div class="overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="p-3 text-left font-medium text-gray-600">Nombre</th>
                        <th class="p-3 text-left font-medium text-gray-600">Email</th>
                        <th class="p-3 text-left font-medium text-gray-600">Rol</th>
                        <th class="p-3 text-left font-medium text-gray-600">Almacén</th>
                        <th class="p-3 text-center font-medium text-gray-600">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="p-3">
                            <div class="font-medium text-gray-800">{{ $user->name }}</div>
                        </td>
                        <td class="p-3 text-gray-500">{{ $user->email }}</td>
                        <td class="p-3">
                            @foreach($user->roles as $role)
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                    {{ $role->name }}
                                </span>
                            @endforeach
                        </td>
                        <td class="p-3 text-gray-500">
                            {{ $user->warehouse?->nombre ?? '—' }}
                        </td>
                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50">
                                    Editar
                                </a>
                                @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar usuario {{ $user->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="px-2 py-1 text-xs rounded border border-red-300 text-red-600 hover:bg-red-50">
                                        Eliminar
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-gray-400">No hay usuarios registrados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </x-wire-card>
</x-admin-layout>