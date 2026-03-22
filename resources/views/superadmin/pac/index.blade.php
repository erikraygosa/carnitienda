@extends('layouts.superadmin-layout')
@section('title', 'PAC / Timbrado')

@section('content')
<div class="space-y-6">
    @foreach($pacs as $pac)
    <div class="bg-gray-900 rounded-xl border {{ $pac->activo ? 'border-indigo-600' : 'border-gray-800' }} overflow-hidden">

        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-2 h-2 rounded-full {{ $pac->activo && $pac->habilitado ? 'bg-emerald-400' : 'bg-gray-600' }}"></div>
                <h2 class="text-white font-semibold">{{ $pac->nombre }}</h2>
                <span class="text-xs px-2 py-0.5 rounded-full font-mono
                    {{ $pac->esSandbox() ? 'bg-amber-900 text-amber-300' : 'bg-emerald-900 text-emerald-300' }}">
                    {{ $pac->ambiente_label }}
                </span>
                @if($pac->activo)
                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-900 text-indigo-300">PAC activo</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <form action="{{ route('superadmin.pac.test', $pac) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex px-3 py-1.5 text-xs rounded-lg border border-gray-700 text-gray-400 hover:bg-gray-800">
                        Probar conexión
                    </button>
                </form>
                @if(! $pac->activo && $pac->habilitado)
                <form action="{{ route('superadmin.pac.activar', $pac) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex px-3 py-1.5 text-xs rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                        Activar este PAC
                    </button>
                </form>
                @endif
            </div>
        </div>

        <form action="{{ route('superadmin.pac.update', $pac) }}" method="POST" class="p-5">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-xs text-gray-500">Nombre display</label>
                    <input type="text" name="nombre" value="{{ $pac->nombre }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs text-gray-500">Ambiente</label>
                    <select name="ambiente"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                        <option value="sandbox"    {{ $pac->ambiente === 'sandbox'    ? 'selected' : '' }}>Sandbox (Pruebas)</option>
                        <option value="produccion" {{ $pac->ambiente === 'produccion' ? 'selected' : '' }}>Producción</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs text-gray-500">API Key <span class="text-gray-600 ml-1">(vacío = no cambiar)</span></label>
                    <input type="password" name="api_key" placeholder="••••••••••••••••"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs text-gray-500">API Secret <span class="text-gray-600 ml-1">(vacío = no cambiar)</span></label>
                    <input type="password" name="api_secret" placeholder="••••••••••••••••"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div class="space-y-1 md:col-span-2">
                    <label class="block text-xs text-gray-500">Notas internas</label>
                    <textarea name="notas" rows="2"
                              class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none resize-none">{{ $pac->notas }}</textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="habilitado" value="1" id="hab_{{ $pac->id }}"
                           {{ $pac->habilitado ? 'checked' : '' }}
                           class="rounded border-gray-600 bg-gray-800 text-indigo-600">
                    <label for="hab_{{ $pac->id }}" class="text-sm text-gray-400">PAC habilitado</label>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit"
                        class="inline-flex px-4 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>
    @endforeach
</div>
@endsection