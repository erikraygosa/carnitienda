<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Carnitienda') }} — Recuperar contraseña</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-cover bg-center bg-no-repeat font-sans antialiased"
      style="background-image:url('{{ asset('back1.png') }}')">

    <div class="min-h-screen bg-black/50 flex items-center justify-center px-4">
        <div class="w-full max-w-md">
            <!-- Card tipo glass (mismo estilo que login) -->
            <div class="rounded-2xl shadow-2xl border border-white/20
                        bg-white/10 backdrop-blur-xl overflow-hidden">

                <!-- Logo -->
                <div class="pt-10 pb-2 flex justify-center">
                    <a href="{{ url('/') }}">
                        <img src="{{ asset('logo.jpg') }}"
                             alt="Logo"
                             class="h-16 w-16 rounded-full object-cover ring-4 ring-white/40 shadow-lg bg-white/60">
                    </a>
                </div>

                <div class="px-6 pb-8 pt-4">
                    <!-- Mensaje -->
                    <p class="mb-4 text-sm text-white/90">
                        ¿Olvidaste tu contraseña? No pasa nada. Ingresa tu correo y te enviaremos un enlace para restablecerla.
                    </p>

                    <!-- Status -->
                    @if (session('status'))
                        <div class="mb-4 font-medium text-sm text-emerald-200">
                            {{ session('status') }}
                        </div>
                    @endif

                    <!-- Errores -->
                    @if ($errors->any())
                        <div class="mb-4">
                            <div class="text-sm text-red-200 font-medium mb-1">Revisa los siguientes errores:</div>
                            <ul class="text-sm text-red-200 list-disc ml-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Formulario -->
                    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-medium text-white">Correo electrónico</label>
                            <input id="email" name="email" type="email" required autofocus autocomplete="username"
                                   value="{{ old('email') }}"
                                   class="mt-1 block w-full rounded-lg border-white/30 bg-white/90
                                          focus:ring-rose-500 focus:border-rose-500">
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <a href="{{ route('login') }}"
                               class="text-sm text-white/90 hover:text-white underline">
                                Volver a iniciar sesión
                            </a>

                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-xl
                                           bg-gradient-to-r from-rose-600 to-red-600
                                           text-white text-sm font-semibold shadow-md
                                           hover:from-rose-700 hover:to-red-700
                                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500">
                                Enviarme el enlace
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <p class="text-center text-xs text-white/80 mt-4">
                © {{ date('Y') }} {{ config('app.name', 'Carnitienda') }} — Todos los derechos reservados
            </p>
        </div>
    </div>

    @livewireScripts
</body>
</html>
