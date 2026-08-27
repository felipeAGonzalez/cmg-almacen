@extends('layouts.app')

@section('page-title', 'Editar proveedor')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    @if (Auth::user()->isAdmin())
                        <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                    @else
                        <li class="breadcrumb-item">Mis almacenes</li>
                    @endif
                    <li class="breadcrumb-item"><a href="{{ route('warehouses.suppliers.index', $warehouse) }}">{{ $warehouse->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Editar proveedor</li>
                </ol>
            </nav>
            <h2>Editar proveedor</h2>
            <p>Actualiza la información del proveedor.</p>
            <span class="badge text-bg-light border mt-2"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
        </div>
    </div>

    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="edit-supplier-title">
        <h3 class="visually-hidden" id="edit-supplier-title">Formulario para editar proveedor</h3>
        @include('suppliers._form')
    </section>
@endsection
