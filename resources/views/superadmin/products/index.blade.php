@extends('layouts.superadmin-layout')
@section('title', 'Catálogo de productos')

@section('content')

@if(session('resultado_importacion'))
    @php $r = session('resultado_importacion'); @endphp
    <div class="mb-6 rounded-xl border border-emerald-700 bg-emerald-900/20 p-5">
        <h3 class="text-emerald-300 font-semibold mb-2">
            <i class="fa-solid fa-circle-check mr-1"></i> Importación terminada
        </h3>
        <div class="text-sm text-gray-300 space-y-1">
            <div>✅ {{ $r['actualizados'] }} producto(s) actualizado(s)</div>
            <div>⏸️ {{ $r['sin_cambios'] }} sin cambios (los valores ya eran iguales o estaban vacíos)</div>
            @if(count($r['no_encontrados']))
                <div class="text-red-400 mt-2">❌ {{ count($r['no_encontrados']) }} fila(s) con ID que no existe:</div>
                <ul class="list-disc list-inside text-xs text-red-400/80 pl-2">
                    @foreach(array_slice($r['no_encontrados'], 0, 20) as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            @endif
            @if(count($r['advertencias']))
                <div class="text-amber-400 mt-2">⚠️ {{ count($r['advertencias']) }} advertencia(s) (esos campos no se tocaron):</div>
                <ul class="list-disc list-inside text-xs text-amber-400/80 pl-2">
                    @foreach(array_slice($r['advertencias'], 0, 20) as $msg)
                        <li>{{ $msg }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Productos en catálogo</div>
        <div class="text-2xl font-semibold text-white mt-1">{{ number_format($totalProductos) }}</div>
    </div>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Sin Clave Prod/Serv (SAT)</div>
        <div class="text-2xl font-semibold {{ $sinClaveProdServ ? 'text-amber-400' : 'text-emerald-400' }} mt-1">
            {{ number_format($sinClaveProdServ) }}
        </div>
    </div>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
        <div class="text-xs text-gray-500 uppercase tracking-wide">Sin Clave Unidad (SAT)</div>
        <div class="text-2xl font-semibold {{ $sinClaveUnidad ? 'text-amber-400' : 'text-emerald-400' }} mt-1">
            {{ number_format($sinClaveUnidad) }}
        </div>
    </div>
</div>

<div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
    <h2 class="text-white font-semibold mb-2">Actualizar datos fiscales en lote</h2>
    <p class="text-sm text-gray-400 mb-5">
        Descarga el catálogo completo en Excel, completa las columnas de <strong>SAT Clave Prod/Serv</strong>,
        <strong>SAT Clave Unidad</strong> y las demás columnas fiscales para los productos que las tengan vacías,
        y vuelve a subir el mismo archivo. Se actualiza por el <strong>ID</strong> — no borres ni reordenes esa columna.
    </p>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('superadmin.products.export') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
            <i class="fa-solid fa-file-arrow-down"></i>
            Exportar catálogo (.xlsx)
        </a>
        <a href="{{ route('superadmin.products.import.form') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-sm rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800">
            <i class="fa-solid fa-file-arrow-up"></i>
            Importar catálogo actualizado
        </a>
    </div>
</div>
@endsection
