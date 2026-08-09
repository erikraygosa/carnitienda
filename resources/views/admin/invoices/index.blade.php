<x-admin-layout
    title="Facturas"
    :breadcrumbs="[
        ['name'=>'Dashboard','url'=>route('admin.dashboard')],
        ['name'=>'Facturas'],
    ]"
>
    <x-slot name="action">
        <a href="{{ route('admin.invoices.create') }}" class="inline-flex px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white">
            Nueva factura
        </a>
    </x-slot>

    @if($timbresInfo)
        @php
            $alertaColor = match($timbresInfo['alerta']) {
                'agotado', 'critico' => 'bg-red-50 border-red-200 text-red-700',
                'advertencia'        => 'bg-amber-50 border-amber-200 text-amber-700',
                default              => 'bg-gray-50 border-gray-200 text-gray-600',
            };
        @endphp
        <div class="mb-4 rounded-lg border {{ $alertaColor }} px-4 py-2 text-sm inline-flex items-center gap-2">
            <span>🧾 Timbres restantes:</span>
            <strong>{{ number_format($timbresInfo['restantes']) }}</strong>
            <span class="opacity-70">de {{ number_format($timbresInfo['contratados']) }} contratados</span>
        </div>
    @endif

    <x-wire-card>
        @livewire('admin.datatables.invoice-table')
    </x-wire-card>

    <script>
        const forms = document.querySelectorAll('.delete-form');
        forms.forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: '¿Eliminar factura?',
                    text: "Esta acción no se puede revertir.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((r) => { if (r.isConfirmed) form.submit(); });
            });
        });
    </script>
</x-admin-layout>

