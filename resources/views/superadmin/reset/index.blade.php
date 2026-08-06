@extends('layouts.superadmin-layout')
@section('title', 'Reiniciar datos operativos')

@section('content')
<div class="space-y-6">

    <div class="bg-amber-900/30 border border-amber-700 rounded-xl p-5 flex gap-3">
        <i class="fa-solid fa-triangle-exclamation text-amber-400 text-xl mt-0.5"></i>
        <div class="text-sm text-amber-200">
            <p class="font-semibold mb-1">Esto borra información real de operación y no se puede deshacer desde la app.</p>
            <p>Se conservan siempre: clientes, productos, categorías, almacenes, usuarios, listas de precios y configuración del sistema.</p>
            <p class="mt-1">Antes de borrar se guarda un respaldo en JSON de cada tabla afectada en <code class="bg-black/30 px-1 rounded">storage/app/reset-backups/&lt;fecha&gt;</code>.</p>
        </div>
    </div>

    <form action="{{ route('superadmin.reset.run') }}" method="POST" id="reset-form" class="space-y-6"
          onsubmit="return confirm('Última confirmación: esto va a borrar datos reales. ¿Continuar?');">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($modules as $key => $mod)
                <label class="bg-gray-900 rounded-xl border border-gray-800 p-4 flex items-start gap-3 cursor-pointer hover:border-gray-700 transition">
                    <input type="checkbox" name="modules[]" value="{{ $key }}"
                           class="mt-1 rounded bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                    <div class="flex-1">
                        <div class="text-white text-sm font-medium">{{ $mod['label'] }}</div>
                        <ul class="mt-2 space-y-0.5">
                            @foreach($mod['tables'] as $table)
                                <li class="flex items-center justify-between text-xs text-gray-500">
                                    <span class="font-mono">{{ $table }}</span>
                                    <span class="text-gray-400">{{ $counts[$table] ?? '—' }} filas</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </label>
            @endforeach
        </div>

        <div class="bg-gray-900 rounded-xl border border-gray-800 p-4 text-xs text-gray-500 flex items-center justify-between">
            <span>Además se limpia la bitácora de auditoría (<span class="font-mono">{{ $auditTable }}</span>) si eliges al menos un módulo, ya que queda apuntando a documentos borrados.</span>
            <span class="text-gray-400">{{ $auditCount ?? '—' }} filas</span>
        </div>

        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <label class="block text-xs text-gray-500 mb-2">
                Escribe <span class="font-mono text-amber-400">REINICIAR</span> para habilitar el botón
            </label>
            <input type="text" name="confirmacion" id="confirm-input" autocomplete="off"
                   class="w-full max-w-xs bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none">
        </div>

        <button type="submit" id="reset-btn" disabled
                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold
                       bg-red-900 text-red-400 cursor-not-allowed transition">
            <i class="fa-solid fa-rotate-left"></i>
            Reiniciar datos seleccionados
        </button>
    </form>
</div>

<script>
(function() {
    const input = document.getElementById('confirm-input');
    const btn   = document.getElementById('reset-btn');
    const checks = document.querySelectorAll('input[name="modules[]"]');

    function refresh() {
        const anyChecked = Array.from(checks).some(c => c.checked);
        const ok = input.value.trim() === 'REINICIAR' && anyChecked;
        btn.disabled = !ok;
        btn.classList.toggle('bg-red-900', !ok);
        btn.classList.toggle('text-red-400', !ok);
        btn.classList.toggle('cursor-not-allowed', !ok);
        btn.classList.toggle('bg-red-600', ok);
        btn.classList.toggle('text-white', ok);
        btn.classList.toggle('hover:bg-red-700', ok);
        btn.classList.toggle('cursor-pointer', ok);
    }

    input.addEventListener('input', refresh);
    checks.forEach(c => c.addEventListener('change', refresh));
})();
</script>
@endsection
