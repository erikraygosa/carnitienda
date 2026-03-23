<x-admin-layout
    title="Despachos"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Despachos'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.dispatches.create') }}"
           class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
            Nuevo despacho
        </a>
    </x-slot>

    {{-- KPIs rápidos --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        @php
            $kpis = [
                ['label'=>'En ruta hoy',   'color'=>'border-violet-400 text-violet-700',
                 'count'=> \App\Models\Dispatch::where('status','EN_RUTA')->whereDate('fecha', today())->count()],
                ['label'=>'Planeados',     'color'=>'border-gray-300 text-gray-600',
                 'count'=> \App\Models\Dispatch::where('status','PLANEADO')->count()],
                ['label'=>'Cerrados hoy',  'color'=>'border-blue-400 text-blue-700',
                 'count'=> \App\Models\Dispatch::where('status','CERRADO')->whereDate('cerrado_at', today())->count()],
                ['label'=>'Cancelados',    'color'=>'border-rose-300 text-rose-600',
                 'count'=> \App\Models\Dispatch::where('status','CANCELADO')->whereDate('fecha', today())->count()],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <x-wire-card>
                <div class="text-xs text-gray-500">{{ $kpi['label'] }}</div>
                <div class="text-2xl font-bold mt-1 {{ $kpi['color'] }}">{{ $kpi['count'] }}</div>
            </x-wire-card>
        @endforeach
    </div>

    <x-wire-card>
        @livewire('admin.datatables.dispatch-table')
    </x-wire-card>

    <script>
        document.addEventListener('livewire:navigated', bindDeleteForms);
        document.addEventListener('DOMContentLoaded', bindDeleteForms);

        function bindDeleteForms() {
            document.querySelectorAll('.delete-form').forEach(f => {
                f.addEventListener('submit', e => {
                    e.preventDefault();
                    Swal.fire({
                        title: '¿Eliminar despacho?',
                        text: 'No podrás revertirlo.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                    }).then(r => { if (r.isConfirmed) f.submit(); });
                });
            });
        }
    </script>

</x-admin-layout>