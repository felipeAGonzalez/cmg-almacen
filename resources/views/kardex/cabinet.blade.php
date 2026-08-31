@extends('layouts.app')
@section('page-title', 'Kardex del gabinete')
@section('content')
    @php($kardexRoute = route('warehouses.cabinets.kardex.index', [$warehouse, $cabinet]))
    <div class="page-heading"><div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">@if(Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif<li class="breadcrumb-item">{{ $warehouse->name }}</li><li class="breadcrumb-item"><a href="{{ route('warehouses.cabinets.index', $warehouse) }}">Gabinetes</a></li><li class="breadcrumb-item">{{ $cabinet->name }}</li><li class="breadcrumb-item active">Kardex</li></ol></nav><h2>Kardex del gabinete</h2><p>Consulta el historial de movimientos del inventario de este gabinete.</p><div class="d-flex flex-wrap gap-2"><span class="badge text-bg-light border"><i class="bi bi-building me-1"></i>Almacén: {{ $warehouse->name }}</span><span class="badge text-bg-light border"><i class="bi bi-archive me-1"></i>Gabinete: {{ $cabinet->name }}</span></div></div></div>
    @include('kardex._filters')
    @include('kardex._table')
@endsection
