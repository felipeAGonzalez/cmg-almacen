@extends('layouts.app')
@php($isCabinet = isset($cabinet))
@section('page-title', $isCabinet ? 'Agregar producto al gabinete' : 'Agregar producto al inventario')
@section('content')
    <div class="page-heading"><div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
            @if (Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif
            <li class="breadcrumb-item">{{ $warehouse->name }}</li>@if ($isCabinet)<li class="breadcrumb-item">{{ $cabinet->name }}</li>@endif<li class="breadcrumb-item active" aria-current="page">Agregar producto</li>
        </ol></nav>
        <h2>{{ $isCabinet ? 'Agregar producto al gabinete' : 'Agregar producto al inventario' }}</h2><p>Configura el producto y sus niveles de stock.</p>
        <div class="d-flex flex-wrap gap-2 mt-2"><span class="badge text-bg-light border">Almacén: {{ $warehouse->name }}</span>@if ($isCabinet)<span class="badge text-bg-light border">Gabinete: {{ $cabinet->name }}</span>@endif</div>
    </div></div>
    <section class="admin-card admin-form-card mx-auto p-3 p-md-4">@include('inventory._form')</section>
@endsection
