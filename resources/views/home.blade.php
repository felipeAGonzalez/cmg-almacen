@extends('layouts.app')

@section('content')

{{-- PERSONALIZACIÓN: reemplaza el nombre del sitio y los colores de la navbar. --}}
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        {{-- PERSONALIZACIÓN: cambia "Mi Aplicación" por el nombre corporativo. --}}
        <a class="navbar-brand fw-semibold" href="{{ route('home') }}">
            {{ config('app.name', 'Mi Aplicación') }}
        </a>

        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-white">
                {{ Auth::user()->name }} {{ Auth::user()->last_name_one }}
            </span>

            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-light btn-sm">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    {{-- PERSONALIZACIÓN: reemplaza este contenido por el dashboard de cada empresa. --}}
                    <h2 class="h4 mb-1">Bienvenido, {{ Auth::user()->name }}</h2>
                    <p class="text-muted mb-0">Has iniciado sesión correctamente.</p>
                </div>
            </div>
        </div>
    </div>
</main>

@endsection
