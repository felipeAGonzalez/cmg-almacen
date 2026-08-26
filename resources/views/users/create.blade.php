@extends('layouts.app')

@section('page-title', 'Nuevo usuario')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Usuarios</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Nuevo usuario</li>
                </ol>
            </nav>
            <h2>Nuevo usuario</h2>
            <p>Captura los datos y accesos del nuevo usuario.</p>
        </div>
    </div>

    <section class="admin-card p-3 p-md-4" aria-labelledby="create-user-title">
        <h3 class="visually-hidden" id="create-user-title">Formulario para crear usuario</h3>
        @include('users._form')
    </section>
@endsection
