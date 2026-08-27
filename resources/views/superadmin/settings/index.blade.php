@extends('layouts.superadmin-layout')
@section('title', 'Configuración del sistema')

@section('content')

@if ($errors->any())
    <div class="mb-6 rounded-xl border border-red-700 bg-red-900/30 px-4 py-3 text-sm text-red-300">
        <p class="font-semibold mb-1">No se guardó — revisa estos campos:</p>
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('superadmin.settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
    @csrf @method('PUT')

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-4">General</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nombre del sistema</label>
                <input type="text" name="app_nombre"
                       value="{{ $general['app.nombre']?->valor ?? config('app.name') }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Zona horaria</label>
                <select name="app_timezone"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    @php $tz = $general['app.timezone']?->valor ?? 'America/Mexico_City'; @endphp
                    <option value="America/Mexico_City"  {{ $tz === 'America/Mexico_City'  ? 'selected' : '' }}>America/Mexico_City</option>
                    <option value="America/Cancun"       {{ $tz === 'America/Cancun'       ? 'selected' : '' }}>America/Cancun</option>
                    <option value="America/Chihuahua"    {{ $tz === 'America/Chihuahua'    ? 'selected' : '' }}>America/Chihuahua</option>
                    <option value="America/Hermosillo"   {{ $tz === 'America/Hermosillo'   ? 'selected' : '' }}>America/Hermosillo</option>
                    <option value="America/Tijuana"      {{ $tz === 'America/Tijuana'      ? 'selected' : '' }}>America/Tijuana</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Logo del sistema</label>
                @php
                    $logoExisteAhora = file_exists(public_path('logo.jpg'));
                    $mostrarLogoTickets = ($general['tickets.mostrar_logo']?->valor ?? '1') === '1';
                @endphp
                @if($logoExisteAhora)
                    <div class="mb-2">
                        <img src="{{ url('logo.jpg') }}?v={{ filemtime(public_path('logo.jpg')) }}" alt="Logo actual"
                             class="h-12 rounded bg-white p-1">
                    </div>
                @endif
                <input type="file" name="app_logo" accept="image/*"
                       class="w-full text-sm text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                              file:text-sm file:font-medium file:bg-indigo-900 file:text-indigo-300 hover:file:bg-indigo-800">
                <p class="text-xs text-gray-500 mt-1">
                    Al subir una imagen aquí se reemplaza public/logo.jpg — el mismo archivo que usan el
                    sidebar/login y los tickets térmicos (POS, Pedidos, Corte de caja).
                </p>

                <label class="flex items-start gap-3 cursor-pointer mt-4">
                    <input type="checkbox" name="tickets_mostrar_logo" value="1"
                           {{ $mostrarLogoTickets ? 'checked' : '' }}
                           class="mt-1 rounded bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                    <span>
                        <span class="block text-sm text-white">Imprimir logo en los tickets</span>
                        <span class="block text-xs text-gray-500 mt-0.5">
                            Activado por defecto. Si lo desactivas, los tickets de POS, Pedidos y Corte de
                            caja se imprimen sin el logo (no afecta al sidebar/login ni a las facturas/remisiones
                            en carta).
                        </span>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-4">Facturación</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Versión CFDI</label>
                <input type="text" name="facturacion_version_cfdi"
                       value="{{ $facturacion['facturacion.version_cfdi']?->valor ?? '4.0' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Exportación default</label>
                <select name="facturacion_exportacion"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    @php $exp = $facturacion['facturacion.exportacion']?->valor ?? '01'; @endphp
                    <option value="01" {{ $exp === '01' ? 'selected' : '' }}>01 — No aplica</option>
                    <option value="02" {{ $exp === '02' ? 'selected' : '' }}>02 — Definitiva</option>
                    <option value="03" {{ $exp === '03' ? 'selected' : '' }}>03 — Temporal</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Alerta timbres restantes</label>
                <input type="number" name="facturacion_alerta_timbres" min="1"
                       value="{{ $facturacion['facturacion.alerta_timbres']?->valor ?? 50 }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-4">Correo electrónico</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Nombre del remitente</label>
                <input type="text" name="correo_from_name"
                       value="{{ $correo['correo.from_name']?->valor ?? '' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Email del remitente</label>
                <input type="email" name="correo_from_address"
                       value="{{ $correo['correo.from_address']?->valor ?? '' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">Autenticación</h3>
        <p class="text-xs text-gray-500 mb-4">Define si los usuarios inician sesión con correo electrónico o solo con nombre de usuario.</p>

        @php
            $loginMode       = $auth['auth.login_mode']?->valor ?? 'email';
            $usernameDomain  = $auth['auth.username_domain']?->valor ?? '';
        @endphp

        <div class="space-y-4">
            <div class="flex gap-6">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="auth_login_mode" value="email"
                           {{ $loginMode === 'email' ? 'checked' : '' }}
                           id="mode_email"
                           class="text-indigo-500 focus:ring-indigo-500">
                    <span class="text-sm text-white">Correo electrónico <span class="text-gray-500">(default)</span></span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="auth_login_mode" value="username"
                           {{ $loginMode === 'username' ? 'checked' : '' }}
                           id="mode_username"
                           class="text-indigo-500 focus:ring-indigo-500">
                    <span class="text-sm text-white">Nombre de usuario</span>
                </label>
            </div>

            <div id="domain-wrap" class="{{ $loginMode === 'username' ? '' : 'hidden' }}">
                <label class="block text-xs text-gray-500 mb-1">Dominio a autocompletar</label>
                <div class="flex items-center gap-2">
                    <span class="text-gray-400 text-sm">usuario @</span>
                    <input type="text" name="auth_username_domain"
                           value="{{ $usernameDomain }}"
                           placeholder="empresa.com"
                           class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none w-60">
                </div>
                <p class="mt-1 text-xs text-gray-600">
                    Ejemplo: si el dominio es <em>empresa.com</em>, el usuario escribe <strong class="text-gray-400">juan</strong> y el sistema lo convierte a <strong class="text-gray-400">juan@empresa.com</strong>.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">WhatsApp (Evolution API)</h3>
        <p class="text-xs text-gray-500 mb-2">Datos del servidor Evolution API usado para enviar remisiones/facturas por WhatsApp. Si falta alguno, el envío por WhatsApp se deshabilita.</p>

        @php
            $waBaseUrl  = $whatsapp['whatsapp.base_url']?->valor ?? '';
            $waInstance = $whatsapp['whatsapp.instance']?->valor ?? '';
            $waApiKey   = $whatsapp['whatsapp.api_key']?->valor ?? '';
            $waFaltan   = array_filter([
                $waBaseUrl  === '' ? 'URL base' : null,
                $waInstance === '' ? 'Instancia' : null,
                $waApiKey   === '' ? 'API Key'   : null,
            ]);
        @endphp
        <p class="text-xs mb-4">
            @if(empty($waFaltan))
                <span class="text-emerald-400">✓ Configurado — los 3 campos tienen valor guardado.</span>
            @else
                <span class="text-amber-400">⚠ Falta guardar: {{ implode(', ', $waFaltan) }}.</span>
            @endif
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">URL base</label>
                <input type="text" name="whatsapp_base_url"
                       value="{{ $whatsapp['whatsapp.base_url']?->valor ?? '' }}"
                       placeholder="https://evo.midominio.com"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Instancia</label>
                <input type="text" name="whatsapp_instance"
                       value="{{ $whatsapp['whatsapp.instance']?->valor ?? '' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">
                    API Key
                    @if(filled($whatsapp['whatsapp.api_key']?->valor ?? null))
                        <span class="text-emerald-500">(ya configurada — deja en blanco para conservarla)</span>
                    @endif
                </label>
                <input type="password" name="whatsapp_api_key" autocomplete="new-password"
                       placeholder="{{ filled($whatsapp['whatsapp.api_key']?->valor ?? null) ? '••••••••••••' : '' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">Chat de asistencia (IA)</h3>
        <p class="text-xs text-gray-500 mb-2">
            Motor de IA usado por el widget de chat flotante para interpretar pedidos en lenguaje
            natural ("Angel Galera, 10 kilos de panza, 10 de pata") y armar el borrador. Compatible
            con la API "chat completions" de OpenAI o cualquier proveedor compatible.
        </p>

        @php
            $aiApiKey  = $asistente['openai.api_key']?->valor ?? '';
            $aiModel   = $asistente['openai.model']?->valor ?? 'gpt-4o-mini';
            $aiBaseUrl = $asistente['openai.base_url']?->valor ?? 'https://api.openai.com/v1';
            $aiAlmacen = $asistente['pedidos.asistente_almacen_id']?->valor ?? '';
        @endphp
        <p class="text-xs mb-4">
            @if(filled($aiApiKey))
                <span class="text-emerald-400">✓ Configurado.</span>
            @else
                <span class="text-amber-400">⚠ Falta guardar la API Key.</span>
            @endif
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">
                    API Key
                    @if(filled($aiApiKey))
                        <span class="text-emerald-500">(ya configurada — deja en blanco para conservarla)</span>
                    @endif
                </label>
                <input type="password" name="openai_api_key" autocomplete="new-password"
                       placeholder="{{ filled($aiApiKey) ? '••••••••••••' : 'sk-...' }}"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Modelo</label>
                <input type="text" name="openai_model" value="{{ $aiModel }}"
                       placeholder="gpt-4o-mini"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">URL base de la API</label>
                <input type="text" name="openai_base_url" value="{{ $aiBaseUrl }}"
                       placeholder="https://api.openai.com/v1"
                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Almacén por defecto para pedidos del chat</label>
                <select name="pedidos_asistente_almacen_id"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    <option value="">— Usar el almacén principal del sistema —</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ (string) $aiAlmacen === (string) $w->id ? 'selected' : '' }}>{{ $w->nombre }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-600">
                    Los pedidos que arma el chat siempre quedan en Borrador — el resto de los datos
                    (entrega, forma de pago, etc.) se ajustan después en el sistema antes de aprobar.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">Precios</h3>
        <p class="text-xs text-gray-500 mb-4">
            Controla si el precio de venta del Punto de Venta (POS) es el mismo en todos los
            almacenes, o si cada almacén puede tener su propio precio.
        </p>

        @php $modoPrecios = $precios['precios.modo']?->valor ?? 'global'; @endphp

        <div class="space-y-3">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="radio" name="precios_modo" value="global"
                       {{ $modoPrecios === 'global' ? 'checked' : '' }}
                       class="mt-1 bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm text-white">Precios globales</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        Un solo precio por producto (el que está en su ficha), igual en todos los almacenes. Comportamiento actual.
                    </span>
                </span>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="radio" name="precios_modo" value="almacen"
                       {{ $modoPrecios === 'almacen' ? 'checked' : '' }}
                       class="mt-1 bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm text-white">Precios por almacén</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        Cada almacén puede tener su propio precio de venta al público. Se gestiona en
                        Inventario → Precios por almacén. El que no tenga precio propio usa el precio
                        de la ficha del producto como respaldo.
                    </span>
                </span>
            </label>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">Pedidos</h3>
        <p class="text-xs text-gray-500 mb-4">Controla qué campos aparecen al crear/editar un pedido.</p>

        @php
            $mostrarIvaPedidos = ($pedidos['pedidos.mostrar_iva']?->valor ?? '1') === '1';
        @endphp

        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="pedidos_mostrar_iva" value="1"
                   {{ $mostrarIvaPedidos ? 'checked' : '' }}
                   class="mt-1 rounded bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block text-sm text-white">Mostrar IVA en pedidos</span>
                <span class="block text-xs text-gray-500 mt-0.5">
                    Activado por defecto. Si lo desactivas, la columna "% IVA" y el renglón de "Impuestos"
                    desaparecen de Pedidos → Crear/Editar (útil si el negocio no maneja IVA en sus productos).
                </span>
            </span>
        </label>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">Reportes</h3>
        <p class="text-xs text-gray-500 mb-4">
            Controla qué pedidos cuenta el aviso "Pedidos pendientes" en Reportes → Liquidaciones.
        </p>

        @php
            $pendientesModo = $reportes['reportes.liquidaciones_pendientes_modo']?->valor ?? 'procesar';
        @endphp

        <div class="space-y-3">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="radio" name="reportes_liquidaciones_pendientes_modo" value="procesar"
                       {{ $pendientesModo === 'procesar' ? 'checked' : '' }}
                       class="mt-1 bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm text-white">Pendientes por procesar (Recomendado)</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        Pedidos Aprobado/Preparando que todavía no pasan por el botón "Procesar".
                    </span>
                </span>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="radio" name="reportes_liquidaciones_pendientes_modo" value="surtir"
                       {{ $pendientesModo === 'surtir' ? 'checked' : '' }}
                       class="mt-1 bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm text-white">Pendientes por surtir</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        Pedidos ya Procesados que siguen en Salida de producto sin terminar de despacharse —
                        el mismo universo que ve el Panel de Surtido.
                    </span>
                </span>
            </label>
        </div>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">Logística</h3>
        <p class="text-xs text-gray-500 mb-4">Controla acciones que pueden saltarse el flujo normal de despacho.</p>

        @php
            $permitirCompletarDirecto = ($logistica['logistica.permitir_completar_traspaso_directo']?->valor ?? '0') === '1';
        @endphp

        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="logistica_permitir_completar_traspaso_directo" value="1"
                   {{ $permitirCompletarDirecto ? 'checked' : '' }}
                   class="mt-1 rounded bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
            <span>
                <span class="block text-sm text-white">Permitir completar un traspaso sin pasar por un despacho</span>
                <span class="block text-xs text-gray-500 mt-0.5">
                    Deshabilitado por defecto. El flujo normal es PENDIENTE → ASIGNADO a un despacho → EN_RUTA → COMPLETADO
                    (desde el propio despacho). Si activas esto, cualquiera con acceso a Traspasos podrá marcarlo como
                    completado directamente, sin que el traspaso haya salido a ruta.
                </span>
            </span>
        </label>
    </div>

    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-white font-semibold mb-1">Etiquetas de surtido</h3>
        <p class="text-xs text-gray-500 mb-4">
            Controla qué imprime el Panel de Surtido: el ticket de siempre (vía navegador),
            o etiquetas ZPL mandadas directo a una impresora térmica de etiquetas (Zebra
            GK420d/GC420d o equivalente compatible ZPL II) en la red local.
        </p>

        @php
            $modoImpresion   = $etiquetas['etiquetas.modo_impresion']?->valor ?? 'ticket';
            $porCajas        = ($etiquetas['etiquetas.imprimir_por_cajas']?->valor ?? '0') === '1';
            $impresoraIp     = $etiquetas['etiquetas.impresora_ip']?->valor ?? '';
            $impresoraPuerto = $etiquetas['etiquetas.impresora_puerto']?->valor ?? '9100';
        @endphp

        <div class="space-y-3">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="radio" name="etiquetas_modo_impresion" value="ticket" id="modo_ticket"
                       {{ $modoImpresion === 'ticket' ? 'checked' : '' }}
                       class="mt-1 bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm text-white">Ticket (default)</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        Comportamiento actual — imprime vía el diálogo del navegador, a cualquier impresora normal o de tickets.
                    </span>
                </span>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="radio" name="etiquetas_modo_impresion" value="zpl" id="modo_zpl"
                       {{ $modoImpresion === 'zpl' ? 'checked' : '' }}
                       class="mt-1 bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm text-white">Etiqueta ZPL</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        El Panel de Surtido manda el ZPL directo por red (socket TCP) a la IP de la
                        impresora — sin diálogo de navegador ni drivers.
                    </span>
                </span>
            </label>
        </div>

        <div id="zpl-wrap" class="mt-4 space-y-4 {{ $modoImpresion === 'zpl' ? '' : 'hidden' }}">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">IP de la impresora</label>
                    <input type="text" name="etiquetas_impresora_ip"
                           value="{{ $impresoraIp }}"
                           placeholder="192.168.1.50"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Puerto</label>
                    <input type="number" name="etiquetas_impresora_puerto" min="1" max="65535"
                           value="{{ $impresoraPuerto }}"
                           class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    <p class="mt-1 text-xs text-gray-600">9100 = estándar RAW en la mayoría de impresoras ZPL.</p>
                </div>
            </div>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="etiquetas_imprimir_por_cajas" value="1"
                       {{ $porCajas ? 'checked' : '' }}
                       class="mt-1 rounded bg-gray-800 border-gray-700 text-indigo-600 focus:ring-indigo-500">
                <span>
                    <span class="block text-sm text-white">Imprimir una etiqueta por cada caja</span>
                    <span class="block text-xs text-gray-500 mt-0.5">
                        Desactivado por defecto (una sola etiqueta resumen por línea de producto). Si lo
                        activas, en el Panel de Surtido aparece un campo de peso por cada caja capturada
                        en "# cajas" — y se imprime una etiqueta individual por caja ("Caja 1 de 3", etc.)
                        con su propio peso.
                    </span>
                </span>
            </label>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit"
                class="px-6 py-2.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 font-medium">
            Guardar configuración
        </button>
    </div>
</form>

{{-- Fuera del form de arriba a propósito: un <form> anidado dentro de otro
     es HTML inválido, y el navegador cierra el <form> exterior en cuanto
     encuentra el </form> del anidado — todo lo que vaya después (Precios,
     Logística, el botón "Guardar configuración") quedaba fuera del form real
     y nunca se enviaba al guardar. Por eso este bloque se movió aquí. --}}
<div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
    <h3 class="text-white font-semibold mb-1">Probar envío de WhatsApp</h3>
    <p class="text-xs text-gray-500 mb-4">Guarda la configuración de arriba primero; luego prueba con un número real.</p>
    <form action="{{ route('superadmin.settings.whatsapp.test') }}" method="POST" class="flex items-end gap-3">
        @csrf
        <div>
            <label class="block text-xs text-gray-500 mb-1">Teléfono (10 dígitos o con lada país)</label>
            <input type="text" name="telefono" placeholder="9991234567"
                   class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none w-52">
        </div>
        <button type="submit"
                class="px-4 py-2 text-sm rounded-lg bg-emerald-700 text-white hover:bg-emerald-600 font-medium">
            Enviar prueba
        </button>
    </form>
</div>

<div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
    <h3 class="text-white font-semibold mb-1">Probar chat de asistencia</h3>
    <p class="text-xs text-gray-500 mb-4">Guarda la configuración de arriba primero; verifica que la API Key y el modelo respondan correctamente.</p>
    <form action="{{ route('superadmin.settings.assistant.test') }}" method="POST">
        @csrf
        <button type="submit"
                class="px-4 py-2 text-sm rounded-lg bg-emerald-700 text-white hover:bg-emerald-600 font-medium">
            Probar conexión
        </button>
    </form>
</div>

<div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
    <h3 class="text-white font-semibold mb-1">Probar impresora de etiquetas</h3>
    <p class="text-xs text-gray-500 mb-4">Guarda la configuración de arriba primero (modo ZPL, IP y puerto); manda una etiqueta de prueba a esa IP.</p>
    <form action="{{ route('superadmin.settings.printer.test') }}" method="POST">
        @csrf
        <button type="submit"
                class="px-4 py-2 text-sm rounded-lg bg-emerald-700 text-white hover:bg-emerald-600 font-medium">
            Imprimir etiqueta de prueba
        </button>
    </form>
</div>

<script>
(function () {
    var radios      = document.querySelectorAll('input[name="auth_login_mode"]');
    var domainWrap  = document.getElementById('domain-wrap');

    radios.forEach(function (r) {
        r.addEventListener('change', function () {
            domainWrap.classList.toggle('hidden', this.value !== 'username');
        });
    });

    var modoRadios = document.querySelectorAll('input[name="etiquetas_modo_impresion"]');
    var zplWrap     = document.getElementById('zpl-wrap');

    modoRadios.forEach(function (r) {
        r.addEventListener('change', function () {
            zplWrap.classList.toggle('hidden', this.value !== 'zpl');
        });
    });
})();
</script>
@endsection