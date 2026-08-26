@extends('layouts.app')

@section('page-title', 'Editar almacén')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Editar almacén</li>
                </ol>
            </nav>
            <h2>Editar almacén</h2>
            <p>Actualiza la información del almacén.</p>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="edit-warehouse-title">
        <h3 class="visually-hidden" id="edit-warehouse-title">Formulario para editar almacén</h3>
        @include('warehouses._form')
    </section>
@endsection
