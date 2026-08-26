@extends('layouts.app')

@section('page-title', 'Editar usuario')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Usuarios</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Editar usuario</li>
                </ol>
            </nav>
            <h2>Editar usuario</h2>
            <p>Actualiza los datos y accesos del usuario.</p>
        </div>
    </div>

    @if ($user->role === \App\Enums\UserRole::LEGACY_USER)
        <div class="alert alert-warning shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>
            Este usuario utiliza un cargo heredado. Selecciona un cargo válido antes de guardar los cambios.
        </div>
    @endif

    <section class="admin-card p-3 p-md-4" aria-labelledby="edit-user-title">
        <h3 class="visually-hidden" id="edit-user-title">Formulario para editar usuario</h3>
        @include('users._form')
    </section>
@endsection
