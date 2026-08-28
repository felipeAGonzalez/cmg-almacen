@extends('layouts.app')
@section('page-title', 'Editar gabinete')
@section('content')
    <div class="page-heading"><div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
            @if (Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif
            <li class="breadcrumb-item"><a href="{{ route('warehouses.cabinets.index', $warehouse) }}">{{ $warehouse->name }}</a></li><li class="breadcrumb-item active" aria-current="page">Editar gabinete</li>
        </ol></nav>
        <h2>Editar gabinete</h2><p>Actualiza la información del gabinete.</p>
        <span class="badge text-bg-light border mt-2"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
    </div></div>
    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="edit-cabinet-title">
        <h3 class="visually-hidden" id="edit-cabinet-title">Formulario para editar gabinete</h3>@include('cabinets._form')
    </section>
@endsection
