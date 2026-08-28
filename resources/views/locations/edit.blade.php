@extends('layouts.app')
@section('page-title', 'Editar ubicación')
@section('content')
    <div class="page-heading"><div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
            @if (Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif
            <li class="breadcrumb-item"><a href="{{ route('warehouses.locations.index', $warehouse) }}">{{ $warehouse->name }}</a></li><li class="breadcrumb-item active" aria-current="page">Editar ubicación</li>
        </ol></nav>
        <h2>Editar ubicación</h2><p>Actualiza la información de la ubicación.</p>
        <span class="badge text-bg-light border mt-2"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
    </div></div>
    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="edit-location-title">
        <h3 class="visually-hidden" id="edit-location-title">Formulario para editar ubicación</h3>@include('locations._form')
    </section>
@endsection
