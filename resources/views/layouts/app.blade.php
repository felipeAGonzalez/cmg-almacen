<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'Inicio') | {{ config('app.name', 'CMG Almacén') }}</title>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('styles')
</head>
<body class="admin-body">
    <aside class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
        <div class="offcanvas-header border-bottom border-light border-opacity-10 d-lg-none">
            <h2 class="offcanvas-title fs-5 text-white" id="adminSidebarLabel">CMG Almacén</h2>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Cerrar menú"></button>
        </div>

        <div class="d-flex h-100 flex-column">
            <a href="{{ route('home') }}" class="admin-brand text-decoration-none">
                <span class="admin-brand-mark" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
                <span>
                    <strong>CMG</strong>
                    <small>Almacén</small>
                </span>
            </a>

            <nav class="admin-nav flex-grow-1" aria-label="Navegación principal">
                <span class="admin-nav-label">MENÚ PRINCIPAL</span>
                <a href="{{ route('home') }}" class="admin-nav-link {{ request()->routeIs('home') ? 'active' : '' }}">
                    <i class="bi bi-house-door" aria-hidden="true"></i>
                    <span>Inicio</span>
                </a>
                @if (Auth::user()->isAdmin())
                    <a href="{{ route('users.index') }}" class="admin-nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <span>Usuarios</span>
                    </a>
                    <a href="{{ route('warehouses.index') }}" class="admin-nav-link {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <span>Almacenes</span>
                    </a>
                @endif
            </nav>

            <div class="admin-sidebar-footer">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <span>Acceso seguro CMG</span>
            </div>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light border d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Abrir menú">
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </button>
                <div>
                    <p class="admin-eyebrow mb-0">CMG Almacén</p>
                    <h1 class="admin-topbar-title mb-0">@yield('page-title', 'Inicio')</h1>
                </div>
            </div>

            <div class="dropdown">
                <button class="btn admin-user-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="admin-user-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                    <span class="d-none d-sm-inline text-start">
                        <strong class="d-block">{{ Auth::user()->name }} {{ Auth::user()->last_name_one }}</strong>
                        <small>{{ Auth::user()->role->label() }}</small>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                    <li class="px-3 py-2 d-sm-none">
                        <strong class="d-block">{{ Auth::user()->name }} {{ Auth::user()->last_name_one }}</strong>
                        <small class="text-body-secondary">{{ Auth::user()->role->label() }}</small>
                    </li>
                    <li class="d-sm-none"><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Cerrar sesión
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="admin-content">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2" aria-hidden="true"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmrmleqXQPpZVkHAhNsFHEqLNk+4"
        crossorigin="anonymous"
    ></script>
    <script src="{{ asset('js/admin.js') }}"></script>
    @stack('scripts')
</body>
</html>
