<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>

    {{-- Bootstrap 5 CDN. PERSONALIZACIÓN: reemplaza por versión local si lo prefieres. --}}
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <style>
        /* PERSONALIZACIÓN: cambia el color de fondo de la página. */
        body { background-color: #f5f5f5; }

        /* PERSONALIZACIÓN: ajusta el ancho máximo de la tarjeta. */
        .login-card { max-width: 420px; width: 100%; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

<div class="login-card px-3">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">

            {{-- PERSONALIZACIÓN: descomenta y pon la ruta a tu logo corporativo. --}}
            {{-- <div class="text-center mb-4">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" style="max-height: 60px;">
            </div> --}}

            {{-- PERSONALIZACIÓN: cambia el nombre de la aplicación. --}}
            <h1 class="h4 text-center mb-4 text-dark fw-semibold">Iniciar sesión</h1>

            {{-- Mensaje de estado (ej. sesión expirada) --}}
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror"
                        autocomplete="username"
                        autofocus
                        required
                    >
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            class="form-control @error('password') is-invalid @enderror"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            id="toggle-password"
                            aria-label="Mostrar u ocultar contraseña"
                            tabindex="-1"
                        >
                            <span id="toggle-icon">&#128065;</span>
                        </button>
                        @error('password')
                            <div class="invalid-feedback order-last">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-4 form-check">
                    <input
                        id="remember_me"
                        type="checkbox"
                        name="remember"
                        class="form-check-input"
                        {{ old('remember') ? 'checked' : '' }}
                    >
                    <label class="form-check-label text-muted" for="remember_me">
                        Recordarme
                    </label>
                </div>

                {{-- PERSONALIZACIÓN: cambia btn-primary por el color primario corporativo. --}}
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Entrar</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmrmleqXQPpZVkHAhNsFHEqLNk+4"
    crossorigin="anonymous"
></script>

<script>
    // Toggle mostrar/ocultar contraseña — sin librerías externas.
    document.getElementById('toggle-password').addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon  = document.getElementById('toggle-icon');
        if (input.type === 'password') {
            input.type   = 'text';
            icon.textContent = '🙈';
        } else {
            input.type   = 'password';
            icon.textContent = '👁';
        }
    });
</script>

</body>
</html>
