@extends('layouts.app')

@section('page-title', 'Detalle de entrada')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
                @if (Auth::user()->isAdmin())
                    <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                @else
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                @endif
                <li class="breadcrumb-item">{{ $warehouse->name }}</li>
                <li class="breadcrumb-item"><a href="{{ route('warehouses.entries.index', $warehouse) }}">Entradas</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $entry->invoice_number }}</li>
            </ol></nav>
            <h2>Detalle de entrada</h2>
            <p>Consulta la factura y los lotes generados durante la recepción.</p>
        </div>
        <a href="{{ route('warehouses.entries.index', $warehouse) }}" class="btn btn-light border"><i class="bi bi-arrow-left me-2"></i>Volver a entradas</a>
    </div>

    <section class="admin-card mb-4">
        <div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Datos de la factura</h3></div>
        <div class="p-4"><div class="row g-4">
            <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Almacén</span><strong class="d-block">{{ $warehouse->name }}</strong></div>
            <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Proveedor</span><strong class="d-block">{{ $entry->supplier->name }}</strong></div>
            <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Número de factura</span><strong class="d-block">{{ $entry->invoice_number }}</strong></div>
            <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Fecha de factura</span><strong class="d-block">{{ $entry->invoice_date->format('d/m/Y') }}</strong></div>
            <div class="col-12"><span class="entry-detail-label">Notas</span><p class="mb-0">{{ $entry->notes ?: 'Sin notas' }}</p></div>
        </div></div>
    </section>

    <section class="admin-card overflow-hidden" aria-labelledby="entry-items-title">
        <div class="admin-card-header"><h3 class="h6 fw-bold mb-0" id="entry-items-title">Partidas recibidas</h3></div>
        <div class="table-responsive"><table class="table admin-table align-middle">
            <thead><tr><th>Producto</th><th>Unidad</th><th>Cantidad recibida</th><th>Costo unitario</th><th>Subtotal</th><th>Lote interno</th><th>Lote fabricante</th><th>Caducidad</th></tr></thead>
            <tbody>
                @foreach ($entry->items as $item)
                    <tr>
                        <td><strong>{{ $item->inventoryItem->product->name }}</strong></td>
                        <td>{{ $item->inventoryItem->product->unit->name }}</td>
                        <td>{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                        <td class="text-nowrap">${{ number_format((float) $item->unit_cost, 4, '.', ',') }}</td>
                        <td class="fw-semibold text-nowrap">${{ number_format((float) $item->subtotal, 2, '.', ',') }}</td>
                        <td><code class="entry-lot">{{ $item->batch->internal_lot }}</code></td>
                        <td>{{ $item->batch->manufacturer_lot ?: 'No indicado' }}</td>
                        <td class="text-nowrap">{{ $item->batch->expiration_date?->format('d/m/Y') ?: 'No aplica' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="entry-show-total"><span>Total de la factura</span><strong>${{ number_format((float) $entry->total, 2, '.', ',') }}</strong></div>
    </section>
@endsection
