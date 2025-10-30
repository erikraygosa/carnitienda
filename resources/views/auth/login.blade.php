<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Carnitienda') }} — Iniciar sesión</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-cover bg-center bg-fixed bg-no-repeat font-sans antialiased"
      style="background-image:url('{{ asset('back1.png') }}')">

    <!-- Capa oscura + centrado -->
    <div class="min-h-screen bg-black/50 flex items-center justify-center px-4">
        <div class="w-full mx-auto" style="max-width: 520px;">
            <div class="w-full rounded-2xl shadow-2xl border border-white/20
                        bg-white/10 backdrop-blur-md overflow-hidden">

                <!-- Logo -->
                <div class="pt-8 pb-2 flex justify-center">
                    <a href="{{ url('/') }}">
                        <img src="{{ asset('logo.jpg') }}"
                             alt="Logo"
                             class="h-16 w-16 rounded-full object-cover ring-4 ring-white/60 shadow-lg bg-white/60">
                    </a>
                </div>

                <!-- Contenido -->
                <div class="px-6 pb-8 pt-4 text-white">
                    @if ($errors->any())
                        <div class="mb-4">
                            <div class="text-sm text-red-400 font-medium mb-1">Revisa los siguientes errores:</div>
                            <ul class="text-sm text-red-400 list-disc ml-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="mb-4 font-medium text-sm text-green-400">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-medium text-white">Correo electrónico</label>
                            <input id="email" name="email" type="email" autocomplete="username" required autofocus
                                   value="{{ old('email') }}"
                                   class="mt-1 block w-full rounded-lg border-gray-300 bg-white/90 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-white">Contraseña</label>
                            <input id="password" name="password" type="password" required autocomplete="current-password"
                                   class="mt-1 block w-full rounded-lg border-gray-300 bg-white/90 text-gray-800 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>

                        <div class="flex items-center">
                            <input id="remember_me" name="remember" type="checkbox"
                                   class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                            <label for="remember_me" class="ml-2 text-sm text-gray-100">Mantener sesión activa</label>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}"
                                   class="text-sm text-gray-200 hover:text-white underline">
                                    ¿Olvidó su contraseña?
                                </a>
                            @endif

                            <button class="inline-flex items-center px-4 py-2 rounded-xl
                            bg-gradient-to-r from-amber-500 to-rose-600
                            text-white font-semibold shadow-md
                            hover:from-amber-600 hover:to-rose-700
                            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                            Iniciar sesión
                            </button>




                        </div>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-white mt-4">
                © {{ date('Y') }} {{ config('app.name', 'Carnitienda') }} — Todos los derechos reservados
            </p>
        </div>
    </div>

    @livewireScripts
</body>
</html>
