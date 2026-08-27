@extends('layouts.app')

@section('page-title', 'Nueva unidad')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('units.index') }}">Unidades</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Nueva unidad</li>
                </ol>
            </nav>
            <h2>Nueva unidad</h2>
            <p>Captura la unidad de medida.</p>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="create-unit-title">
        <h3 class="visually-hidden" id="create-unit-title">Formulario para crear unidad</h3>
        @include('units._form')
    </section>
@endsection
