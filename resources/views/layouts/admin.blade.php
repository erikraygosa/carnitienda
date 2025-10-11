@props([
    'breadcrumbs' => []
    ])


<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

      

        <script src="https://kit.fontawesome.com/c2ec59aa84.js" crossorigin="anonymous"></script>

         <wireui:scripts />

           <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900">
    {{-- NAVBAR fijo arriba --}}
    @include('layouts.includes.admin.navigation')

    {{-- SIDEBAR fijo a la izquierda --}}
    @include('layouts.includes.admin.sidebar')

    {{-- CONTENIDO PRINCIPAL
         - pt-20 para compensar navbar fijo (coincide con tu sidebar pt-20)
         - sm:ml-64 para compensar sidebar fijo a partir de sm
    --}}
    <main class="min-h-screen pt-20 p-4 sm:ml-64 bg-gray-100 dark:bg-gray-900">
        {{-- Encabezado: breadcrumb + acciones a la derecha --}}
        <div class="flex flex-col gap-3 md:flex-row md:items-center">
            @include('layouts.includes.admin.breadcrumb')

            @isset($action)
                <div class="md:ml-auto">
                    {{ $action }} {{-- botones de acción --}}
                </div>
            @endisset
        </div>

        {{-- Slot de contenido de la página --}}
        <div class="mt-4">
            {{ $slot }}
        </div>
    </main>

    {{-- Modals Livewire/Jetstream --}}
    @stack('modals')

    {{-- Livewire --}}
    @livewireScripts

    {{-- Flowbite (componentes UI como collapse del sidebar) --}}
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>

    {{-- Pila de scripts adicionales por vista --}}
    @stack('scripts')
</body>

</html>
