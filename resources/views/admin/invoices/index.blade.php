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
        // ── Cancelar CFDI desde el listado — antes era un confirm() nativo
        //    feo y ni siquiera mandaba el motivo (la petición al servidor
        //    siempre fallaba por "campo motivo obligatorio"). Ahora pide
        //    motivo (y UUID de sustitución si aplica) con el mismo estilo
        //    del resto de la app.
        window.cancelarCfdiDesdeListado = function (btn) {
            var form = btn.closest('form');
            Swal.fire({
                title: '¿Cancelar esta factura en el SAT?',
                icon: 'warning',
                html:
                    '<div style="text-align:left">' +
                        '<label style="font-size:12px;color:#666;">Motivo de cancelación</label>' +
                        '<select id="swal-motivo" class="swal2-select" style="width:100%;margin:4px 0;display:block;">' +
                            '<option value="02">02 — Comprobante emitido con errores sin relación</option>' +
                            '<option value="01">01 — Comprobante emitido con errores con relación</option>' +
                            '<option value="03">03 — No se llevó a cabo la operación</option>' +
                            '<option value="04">04 — Operación nominativa relacionada en factura global</option>' +
                        '</select>' +
                        '<div id="swal-wrap-folio" style="display:none;margin-top:8px;">' +
                            '<label style="font-size:12px;color:#666;">UUID de la factura que la sustituye</label>' +
                            '<input id="swal-folio" class="swal2-input" style="width:100%;margin:4px 0;" placeholder="00000000-0000-0000-0000-000000000000">' +
                        '</div>' +
                    '</div>',
                showCancelButton: true,
                confirmButtonText: 'Cancelar CFDI',
                confirmButtonColor: '#d33',
                cancelButtonText: 'Volver',
                didOpen: function () {
                    var sel  = document.getElementById('swal-motivo');
                    var wrap = document.getElementById('swal-wrap-folio');
                    sel.addEventListener('change', function () {
                        wrap.style.display = sel.value === '01' ? 'block' : 'none';
                    });
                },
                preConfirm: function () {
                    var motivo = document.getElementById('swal-motivo').value;
                    var folio  = document.getElementById('swal-folio').value.trim();
                    if (motivo === '01' && !folio) {
                        Swal.showValidationMessage('El motivo 01 requiere el UUID de la factura que sustituye a esta.');
                        return false;
                    }
                    return { motivo: motivo, folio: folio };
                }
            }).then(function (result) {
                if (!result.isConfirmed) return;
                form.querySelector('input[name="motivo"]').value = result.value.motivo;
                form.querySelector('input[name="folio_sustitucion"]').value = result.value.folio;
                form.submit();
            });
        };

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

