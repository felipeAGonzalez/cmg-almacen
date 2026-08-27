@extends('layouts.app')

@section('page-title', 'Nueva categoría')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Categorías</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Nueva categoría</li>
                </ol>
            </nav>
            <h2>Nueva categoría</h2>
            <p>Captura la información de la categoría.</p>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="create-category-title">
        <h3 class="visually-hidden" id="create-category-title">Formulario para crear categoría</h3>
        @include('categories._form')
    </section>
@endsection
