@extends('layouts.app')
@section('page-title', 'Kardex')
@section('content')
    @php($kardexRoute = route('warehouses.kardex.index', $warehouse))
    <div class="page-heading"><div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">@if(Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif<li class="breadcrumb-item">{{ $warehouse->name }}</li><li class="breadcrumb-item active">Kardex</li></ol></nav><h2>Kardex</h2><p>Consulta el historial de movimientos del inventario de este almacén.</p><span class="badge text-bg-light border"><i class="bi bi-building me-1"></i>Almacén: {{ $warehouse->name }}</span></div></div>
    @include('kardex._filters')
    @include('kardex._table')
@endsection
