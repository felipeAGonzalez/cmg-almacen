@extends('layouts.app')

@section('page-title', 'Nuevo almacén')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Nuevo almacén</li>
                </ol>
            </nav>
            <h2>Nuevo almacén</h2>
            <p>Captura la información del nuevo almacén.</p>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="create-warehouse-title">
        <h3 class="visually-hidden" id="create-warehouse-title">Formulario para crear almacén</h3>
        @include('warehouses._form')
    </section>
@endsection
