@extends('layouts.app')

@section('page-title', 'Editar unidad')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('units.index') }}">Unidades</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Editar unidad</li>
                </ol>
            </nav>
            <h2>Editar unidad</h2>
            <p>Actualiza la información de la unidad.</p>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="edit-unit-title">
        <h3 class="visually-hidden" id="edit-unit-title">Formulario para editar unidad</h3>
        @include('units._form')
    </section>
@endsection
