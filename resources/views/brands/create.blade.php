@extends('layouts.app')

@section('page-title', 'Nueva marca')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('brands.index') }}">Marcas</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Nueva marca</li>
                </ol>
            </nav>
            <h2>Nueva marca</h2>
            <p>Captura la información de la marca.</p>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="create-brand-title">
        <h3 class="visually-hidden" id="create-brand-title">Formulario para crear marca</h3>
        @include('brands._form')
    </section>
@endsection
