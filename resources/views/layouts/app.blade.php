<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- PERSONALIZACIÓN: cambia el título en config/app.php (APP_NAME en .env). --}}
    <title>{{ config('app.name', 'Mi Aplicación') }}</title>

    {{-- Bootstrap 5 CDN. PERSONALIZACIÓN: reemplaza por versión local en producción. --}}
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    {{-- PERSONALIZACIÓN: añade aquí los estilos CSS corporativos de cada empresa. --}}
    @stack('styles')
</head>
<body class="bg-light">

    @yield('content')

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmrmleqXQPpZVkHAhNsFHEqLNk+4"
        crossorigin="anonymous"
    ></script>

    @stack('scripts')
</body>
</html>
