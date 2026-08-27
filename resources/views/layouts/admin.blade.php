@props([
    'title' => config('app.name', 'Laravel'),
    'breadcrumbs' => []
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Evita que Chrome ofrezca traducir el panel (Google Translate) --}}
    <meta name="google" content="notranslate">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- PWA: pantalla completa al agregar a inicio --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ $title }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="manifest" href="/manifest.json">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    {{-- Fontawesome (CDN público, no depende de un Kit con cuenta/suscripción) --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    {{-- Livewire styles --}}
    @livewireStyles

    <!-- Vite: CSS + JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('css')
</head>

<body class="font-sans antialiased bg-gray-50">

    @include('layouts.includes.admin.navigation')

    @include('layouts.includes.admin.sidebar')

   <div id="main-content" class="p-4 transition-[margin] duration-300 ease-in-out">

        <div class="mt-14 flex items-center">

            @include('layouts.includes.admin.breadcrumb')

            @isset($action)
                <div class="ml-auto">
                    {{ $action }}
                </div>
            @endisset
        </div>

        {{ $slot }}

    </div>

    @stack('modals')

    {{-- Livewire primero --}}
    @livewireScripts

    {{-- WireUI después de Livewire (usa el Alpine de Livewire) --}}
    <wireui:scripts />

    {{-- Flowbite --}}
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>

    @if(session('swal'))
    <script>
        Swal.fire(@json(session('swal')));
    </script>
    @endif

    @auth
        @include('partials.assistant-widget')
    @endauth

    @stack('js')

</body>

</html>