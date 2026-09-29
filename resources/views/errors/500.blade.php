<!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="google" content="notranslate">
    <title>Error del servidor — {{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
          integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
          crossorigin="anonymous" referrerpolicy="no-referrer" />

    @php
        $logoPath   = public_path('logo.jpg');
        $logoExists = file_exists($logoPath);
        if ($logoExists) {
            $logoSrc = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
        }
    @endphp

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Figtree', ui-sans-serif, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fafaf9 0%, #f3f4f6 100%);
            color: #1f2937;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 50px -12px rgba(0,0,0,0.15);
            padding: 40px 36px;
            text-align: center;
        }
        .logo { max-width: 130px; max-height: 70px; object-fit: contain; margin: 0 auto 20px; display: block; }
        .icon-wrap {
            width: 76px; height: 76px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .icon-wrap i { font-size: 32px; color: #b45309; }
        .code { font-size: 13px; font-weight: 600; letter-spacing: 2px; color: #b45309; text-transform: uppercase; margin-bottom: 6px; }
        h1 { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 10px; }
        p.desc { font-size: 14px; color: #6b7280; line-height: 1.6; margin-bottom: 8px; }
        .actions { margin-top: 28px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .15s;
            cursor: pointer;
            border: none;
        }
        .btn:hover { opacity: 0.88; }
        .btn-primary { background: #4f46e5; color: #fff; }
        .btn-secondary { background: #f3f4f6; color: #374151; }
        .footer-note { margin-top: 24px; font-size: 12px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="card">
        @if($logoExists)
            <img src="{{ $logoSrc }}" alt="Logo" class="logo">
        @endif

        <div class="icon-wrap">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>

        <div class="code">Error 500</div>
        <h1>Algo salió mal</h1>
        <p class="desc">
            Tuvimos un problema inesperado al procesar tu solicitud. Ya se avisó al equipo
            para revisarlo. Intenta de nuevo en un momento.
        </p>

        <div class="actions">
            <a href="{{ url('/') }}" class="btn btn-primary">
                <i class="fa-solid fa-house"></i> Ir al inicio
            </a>
            <button onclick="history.back()" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Regresar
            </button>
        </div>

        <div class="footer-note">{{ config('app.name', 'Sistema') }}</div>
    </div>
</body>
</html>
