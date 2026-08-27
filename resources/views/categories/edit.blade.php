@extends('layouts.app')

@section('page-title', 'Editar categoría')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Categorías</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Editar categoría</li>
                </ol>
            </nav>
            <h2>Editar categoría</h2>
            <p>Actualiza la información de la categoría.</p>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="edit-category-title">
        <h3 class="visually-hidden" id="edit-category-title">Formulario para editar categoría</h3>
        @include('categories._form')
    </section>
@endsection
