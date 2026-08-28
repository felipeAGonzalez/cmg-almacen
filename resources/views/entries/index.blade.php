@extends('layouts.app')

@section('page-title', 'Entradas')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    @if (Auth::user()->isAdmin())
                        <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                    @endif
                    <li class="breadcrumb-item">{{ $warehouse->name }}</li>
                    <li class="breadcrumb-item active" aria-current="page">Entradas</li>
                </ol>
            </nav>
            <h2>Entradas</h2>
            <p>Consulta las entradas de mercancía registradas en este almacén.</p>
            <span class="badge text-bg-light border"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
        </div>
        <a href="{{ route('warehouses.entries.create', $warehouse) }}" class="btn btn-primary">
            <i class="bi bi-receipt me-2" aria-hidden="true"></i>Registrar entrada
        </a>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="entry-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="entry-list-title">Entradas registradas</h3>
            <p class="small text-body-secondary mb-0">{{ $entries->total() }} {{ $entries->total() === 1 ? 'entrada' : 'entradas' }}</p>
        </div>

        @if ($entries->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
                <h3 class="h5">No hay entradas registradas en este almacén.</h3>
                <p class="text-body-secondary mb-4">Registra una entrada cuando recibas mercancía de un proveedor.</p>
                <a href="{{ route('warehouses.entries.create', $warehouse) }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2" aria-hidden="true"></i>Registrar entrada
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead><tr><th>Fecha</th><th>Factura</th><th>Proveedor</th><th>Partidas</th><th>Total</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="text-nowrap">{{ $entry->invoice_date->format('d/m/Y') }}</td>
                                <td><strong>{{ $entry->invoice_number }}</strong></td>
                                <td>{{ $entry->supplier->name }}</td>
                                <td>{{ $entry->items_count }} {{ $entry->items_count === 1 ? 'partida' : 'partidas' }}</td>
                                <td class="fw-semibold text-nowrap">${{ number_format((float) $entry->total, 2, '.', ',') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('warehouses.entries.show', [$warehouse, $entry]) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye me-1" aria-hidden="true"></i>Ver detalle
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($entries->hasPages())
                <div class="border-top px-3 py-3">{{ $entries->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        @endif
    </section>
@endsection
