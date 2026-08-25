@extends('layouts.superadmin-layout')
@section('title', 'Importar catálogo')

@section('content')
<div class="mb-4">
    <a href="{{ route('superadmin.products.index') }}" class="text-xs text-gray-500 hover:text-gray-300">
        <i class="fa-solid fa-arrow-left mr-1"></i> Catálogo de productos
    </a>
</div>

@if($errors->any())
<div class="mb-4 rounded-lg border border-red-700 bg-red-900/30 px-4 py-3 text-red-300 text-sm">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<div class="bg-gray-900 rounded-xl border border-gray-800 p-6 max-w-xl">
    <h2 class="text-white font-semibold mb-2">Importar catálogo actualizado</h2>
    <p class="text-sm text-gray-400 mb-5">
        Sube el mismo archivo .xlsx que descargaste en "Exportar catálogo", ya con las columnas fiscales
        completadas. Los productos se identifican por la columna <strong>ID</strong> — filas sin ID válido se ignoran.
    </p>

    <form action="{{ route('superadmin.products.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div>
            <label class="block text-xs text-gray-500 mb-1">Archivo (.xlsx, .xls o .csv)</label>
            <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required
                   class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-indigo-600 file:text-white file:text-xs hover:file:bg-indigo-700 focus:border-indigo-500 focus:outline-none">
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('superadmin.products.index') }}" class="px-4 py-2 text-sm text-gray-400 hover:text-white">
                Cancelar
            </a>
            <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                Subir e importar
            </button>
        </div>
    </form>
</div>
@endsection
