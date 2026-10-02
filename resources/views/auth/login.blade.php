<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso | CMG Almacén</title>

    {{-- Bootstrap 5 CDN. PERSONALIZACIÓN: reemplaza por versión local si lo prefieres. --}}
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >

    <style>
        :root {
            --warehouse-navy: #10243f;
            --warehouse-blue: #2563a9;
            --warehouse-cyan: #45b8d8;
        }

        body {
            background:
                radial-gradient(circle at 12% 15%, rgba(69, 184, 216, .2), transparent 25%),
                linear-gradient(135deg, #0d1d33 0%, #173d66 52%, #eef3f8 52%);
        }

        .login-card { max-width: 980px; width: 100%; }
        .login-card .card { border-radius: 1.25rem; overflow: hidden; }
        .warehouse-identity {
            background: linear-gradient(145deg, var(--warehouse-navy), #194873);
            color: #fff;
            min-height: 570px;
            padding: clamp(2rem, 5vw, 4.5rem);
            position: relative;
        }
        .warehouse-identity::after {
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 50%;
            bottom: -170px;
            content: '';
            height: 390px;
            position: absolute;
            right: -110px;
            width: 390px;
        }
        .warehouse-mark {
            align-items: center;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 1rem;
            display: inline-flex;
            height: 58px;
            justify-content: center;
            width: 58px;
        }
        .warehouse-mark svg { height: 32px; width: 32px; }
        .warehouse-kicker {
            color: #9edced;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .warehouse-title { font-size: clamp(2.6rem, 5vw, 3.75rem); font-weight: 750; letter-spacing: -.04em; }
        .warehouse-copy { color: #c8d8e8; font-size: 1.05rem; line-height: 1.7; }
        .warehouse-feature { color: #e7f2fb; font-size: .9rem; }
        .warehouse-feature span {
            background: rgba(69, 184, 216, .18);
            border-radius: 50%;
            color: #9edced;
            display: inline-grid;
            height: 28px;
            margin-right: .6rem;
            place-items: center;
            width: 28px;
        }
        .login-form-panel { align-items: center; display: flex; padding: clamp(2rem, 5vw, 4rem); }
        .login-form-wrap { margin: auto; max-width: 390px; width: 100%; }
        .login-eyebrow { color: var(--warehouse-blue); font-size: .75rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .login-form-wrap .form-control { min-height: 48px; }
        .login-form-wrap .btn-primary { background: var(--warehouse-blue); border-color: var(--warehouse-blue); min-height: 48px; }
        .security-note { border-top: 1px solid #e5eaf0; color: #6b7788; font-size: .78rem; margin-top: 2rem; padding-top: 1rem; text-align: center; }

        @media (max-width: 767.98px) {
            body { background: #fff; }
            .login-card { padding: 0 !important; }
            .login-card .card { border-radius: 0; min-height: 100vh; }
            .warehouse-identity { min-height: auto; padding: 1.5rem; }
            .warehouse-title { font-size: 2rem; margin-bottom: 0 !important; }
            .warehouse-copy, .warehouse-features { display: none; }
            .login-form-panel { padding: 2.5rem 1.5rem; }
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

<div class="login-card px-3">
    <div class="card shadow-sm border-0">
        <div class="row g-0">
            <section class="col-md-6 warehouse-identity d-flex flex-column justify-content-between" aria-label="CMG Almacén">
                <div class="position-relative z-1">
                    <div class="d-flex align-items-center gap-3 mb-md-5">
                        <span class="warehouse-mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                <path d="M3 9.5 12 4l9 5.5v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>
                                <path d="M7 20.5v-7h10v7M7 13.5h10M10 16.5h4"/>
                            </svg>
                        </span>
                        <div><div class="warehouse-kicker">Sistema CMG</div><div class="fw-semibold fs-5">Control de inventario clínico</div></div>
                    </div>
                    <div class="warehouse-title mt-4 mb-3">Almacén</div>
                    <p class="warehouse-copy mb-0">Gestión segura de existencias, lotes y solicitudes de insumos para la operación clínica.</p>
                </div>
                <div class="warehouse-features position-relative z-1 d-grid gap-3 mt-5">
                    <div class="warehouse-feature"><span>✓</span>Inventario y trazabilidad por lote</div>
                    <div class="warehouse-feature"><span>✓</span>Vales y reposiciones operativas</div>
                    <div class="warehouse-feature"><span>✓</span>Acceso exclusivo para personal autorizado</div>
                </div>
            </section>

            <section class="col-md-6 login-form-panel">
            <div class="login-form-wrap">

            {{-- PERSONALIZACIÓN: descomenta y pon la ruta a tu logo corporativo. --}}
            {{-- <div class="text-center mb-4">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" style="max-height: 60px;">
            </div> --}}

            <div class="login-eyebrow mb-2">Portal de Almacén</div>
            <h1 class="h3 mb-2 text-dark fw-semibold">Bienvenido</h1>
            <p class="text-secondary mb-4">Ingresa con tu cuenta de CMG Almacén.</p>

            {{-- Mensaje de estado (ej. sesión expirada) --}}
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
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
                    <button type="submit" class="btn btn-primary">Acceder a Almacén</button>
                </div>
            </form>
            <div class="security-note">Acceso seguro · CMG Almacén</div>
            </div>
            </section>
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
