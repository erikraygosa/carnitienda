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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body
        class="min-h-screen bg-cover bg-center bg-no-repeat font-sans text-gray-900 antialiased"
        style="background-image: url('{{ asset('back2.jpg') }}');"
    >
        <!-- Capa de oscurecimiento opcional -->
        <div class="min-h-screen bg-black/50 flex items-center justify-center">
            {{ $slot }}
        </div>

        @livewireScripts
    </body>
</html>
