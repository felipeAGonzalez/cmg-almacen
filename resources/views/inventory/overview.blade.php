@extends('layouts.app')

@section('page-title', 'Existencias')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Existencias</h2>
            <p>Consulta productos, niveles de stock y lotes con caducidad en los almacenes autorizados.</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al inicio</a>
    </div>

    @include('inventory._filters', ['filterAction' => route('inventory.index')])

    <section class="admin-card overflow-hidden" aria-labelledby="inventory-overview-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="inventory-overview-title">Productos encontrados</h3>
            <p class="small text-body-secondary mb-0">{{ $inventoryItems->total() }} {{ $inventoryItems->total() === 1 ? 'producto' : 'productos' }}</p>
        </div>

        @if ($inventoryItems->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-search"></i></span>
                <h3 class="h5">No se encontraron productos.</h3>
                <p class="text-body-secondary mb-4">Prueba con otros criterios de búsqueda o caducidad.</p>
                <a href="{{ route('inventory.index') }}" class="btn btn-light border">Limpiar filtros</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead><tr><th>Producto</th><th>Inventario</th><th>Existencia usable</th><th>Existencia vencida</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($inventoryItems as $inventoryItem)
                        @php($isCabinet = $inventoryItem->stockable instanceof \App\Models\Cabinet)
                        @php($warehouse = $isCabinet ? $inventoryItem->stockable->warehouse : $inventoryItem->stockable)
                        @php($detailRoute = $isCabinet
                            ? route('warehouses.cabinets.inventory.index', [$warehouse, $inventoryItem->stockable])
                            : route('warehouses.inventory.index', $warehouse))
                        <tr>
                            <td><strong class="d-block">{{ $inventoryItem->product->name }}</strong><span class="small text-body-secondary">{{ $inventoryItem->product->code ?: $inventoryItem->product->barcode }}</span></td>
                            <td><span class="badge text-bg-light border">{{ $isCabinet ? 'Gabinete' : 'Almacén' }}</span><span class="d-block mt-1">{{ $inventoryItem->stockable->name }}</span>@if ($isCabinet)<small class="text-body-secondary">{{ $warehouse->name }}</small>@endif</td>
                            <td>{{ \App\Models\InventoryItem::formatQuantity($inventoryItem->usableStock()) }} {{ $inventoryItem->product->unit->name }}</td>
                            <td class="{{ bccomp($inventoryItem->expiredStock(), '0', 3) > 0 ? 'text-danger fw-semibold' : '' }}">{{ \App\Models\InventoryItem::formatQuantity($inventoryItem->expiredStock()) }}</td>
                            <td><span class="badge {{ $inventoryItem->stockStatusBadgeClass() }}">{{ $inventoryItem->stockStatusLabel() }}</span></td>
                            <td class="text-end"><a href="{{ $detailRoute }}" class="btn btn-sm btn-outline-primary">Ver inventario</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($inventoryItems->hasPages())<div class="border-top px-3 py-3">{{ $inventoryItems->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>
@endsection
