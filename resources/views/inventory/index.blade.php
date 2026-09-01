@extends('layouts.app')

@php($isCabinet = isset($cabinet))
@php($inventoryCreateRoute = $isCabinet ? route('warehouses.cabinets.inventory.create', [$warehouse, $cabinet]) : route('warehouses.inventory.create', $warehouse))

@section('page-title', $isCabinet ? 'Inventario del gabinete' : 'Inventario')

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
                    @if ($isCabinet)
                        @if (Auth::user()->isAdmin())
                            <li class="breadcrumb-item"><a href="{{ route('warehouses.cabinets.index', $warehouse) }}">Gabinetes</a></li>
                        @endif
                        <li class="breadcrumb-item">{{ $cabinet->name }}</li>
                    @endif
                    <li class="breadcrumb-item active" aria-current="page">Inventario</li>
                </ol>
            </nav>
            <h2>{{ $isCabinet ? 'Inventario del gabinete' : 'Inventario' }}</h2>
            <p>{{ $isCabinet ? 'Configura los productos y niveles de stock de este gabinete.' : 'Configura los productos y niveles de stock de este almacén.' }}</p>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <span class="badge text-bg-light border"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
                @if ($isCabinet)
                    <span class="badge text-bg-light border"><i class="bi bi-archive me-1" aria-hidden="true"></i>Gabinete: {{ $cabinet->name }}</span>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if ($isCabinet && Auth::user()->isAdmin())
                <a href="{{ route('warehouses.cabinets.index', $warehouse) }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a gabinetes</a>
            @elseif (! $isCabinet && Auth::user()->isAdmin())
                <a href="{{ route('warehouses.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a almacenes</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al inicio</a>
            @endif
            <a href="{{ $inventoryCreateRoute }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar producto</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm" role="note">
        <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>La existencia se calcula a partir de los lotes disponibles. Los lotes vencidos se muestran por separado y no cuentan como existencia utilizable.
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="inventory-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="inventory-list-title">Productos configurados</h3>
            <p class="small text-body-secondary mb-0">{{ $inventoryItems->total() }} {{ $inventoryItems->total() === 1 ? 'producto' : 'productos' }}</p>
        </div>

        @if ($inventoryItems->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-clipboard-data"></i></span>
                <h3 class="h5">{{ $isCabinet ? 'No hay productos configurados en este gabinete.' : 'No hay productos configurados en este inventario.' }}</h3>
                <p class="text-body-secondary mb-4">Agrega los productos que se manejarán en este punto de inventario.</p>
                <a href="{{ $inventoryCreateRoute }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar producto</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Producto</th>
                            <th scope="col">Unidad</th>
                            @unless ($isCabinet)<th scope="col">Ubicación</th>@endunless
                            <th scope="col">Existencia</th>
                            <th scope="col">Mínimo</th>
                            <th scope="col">Máximo</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($inventoryItems as $inventoryItem)
                            @php($editRoute = $isCabinet ? route('warehouses.cabinets.inventory.edit', [$warehouse, $cabinet, $inventoryItem]) : route('warehouses.inventory.edit', [$warehouse, $inventoryItem]))
                            @php($destroyRoute = $isCabinet ? route('warehouses.cabinets.inventory.destroy', [$warehouse, $cabinet, $inventoryItem]) : route('warehouses.inventory.destroy', [$warehouse, $inventoryItem]))
                            @php($adjustmentsRoute = $isCabinet ? route('warehouses.cabinets.inventory.adjustments.index', [$warehouse, $cabinet, $inventoryItem]) : route('warehouses.inventory.adjustments.index', [$warehouse, $inventoryItem]))
                            <tr>
                                <td>
                                    <strong class="d-block fw-semibold">{{ $inventoryItem->product->name }}</strong>
                                    <span class="small text-body-secondary">
                                        @if ($inventoryItem->product->code) Interno: {{ $inventoryItem->product->code }} @endif
                                        @if ($inventoryItem->product->code && $inventoryItem->product->barcode) · @endif
                                        @if ($inventoryItem->product->barcode) Barras: {{ $inventoryItem->product->barcode }} @endif
                                    </span>
                                </td>
                                <td>{{ $inventoryItem->product->unit->name }}</td>
                                @unless ($isCabinet)<td>{{ $inventoryItem->location?->name ?? 'Sin ubicación' }}</td>@endunless
                                <td>
                                    <strong class="d-block fs-6">{{ \App\Models\InventoryItem::formatQuantity($inventoryItem->usableStock()) }}</strong>
                                    @if (bccomp($inventoryItem->expiredStock(), '0', 3) > 0)
                                        <span class="small text-danger d-block">{{ \App\Models\InventoryItem::formatQuantity($inventoryItem->expiredStock()) }} vencidas</span>
                                        <span class="small text-body-secondary d-block">Total físico: {{ \App\Models\InventoryItem::formatQuantity($inventoryItem->physicalStock()) }}</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ \App\Models\InventoryItem::formatQuantity($inventoryItem->minimum_stock) }}</td>
                                <td class="text-nowrap">{{ \App\Models\InventoryItem::formatQuantity($inventoryItem->maximum_stock) }}</td>
                                <td><span class="badge {{ $inventoryItem->stockStatusBadgeClass() }}">{{ $inventoryItem->stockStatusLabel() }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ $adjustmentsRoute }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Ajustes</a>
                                    <a href="{{ $editRoute }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#removeInventoryItemModal" data-modal-name="{{ $inventoryItem->product->name }}" data-modal-name-target="#removeInventoryItemName" data-modal-delete-url="{{ $destroyRoute }}" data-modal-form-target="#removeInventoryItemForm"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Retirar del inventario</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($inventoryItems->hasPages())
                <div class="border-top px-3 py-3">{{ $inventoryItems->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="removeInventoryItemModal" tabindex="-1" aria-labelledby="removeInventoryItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
            <div class="modal-header"><h2 class="modal-title fs-5" id="removeInventoryItemModalLabel">{{ $isCabinet ? '¿Retirar producto del gabinete?' : '¿Retirar producto del inventario?' }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="mb-2">Se eliminará la configuración de este producto. Esta acción no representa una salida física de mercancía.</p><p class="fw-semibold mb-0" id="removeInventoryItemName"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="removeInventoryItemForm">@csrf @method('DELETE')<button type="submit" class="btn btn-danger">Retirar configuración</button></form>
            </div>
        </div></div>
    </div>
@endsection
