@extends('layouts.app')

@section('page-title', 'Almacenes')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Almacenes</h2>
            <p>Administra los almacenes disponibles en el sistema.</p>
        </div>
        <a href="{{ route('warehouses.create') }}" class="btn btn-primary">
            <i class="bi bi-building-add me-2" aria-hidden="true"></i>Nuevo almacén
        </a>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="warehouse-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="warehouse-list-title">Almacenes registrados</h3>
            <p class="small text-body-secondary mb-0">{{ $warehouses->total() }} {{ $warehouses->total() === 1 ? 'almacén' : 'almacenes' }}</p>
        </div>

        @if ($warehouses->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-building"></i></span>
                <h3 class="h5">No hay almacenes registrados.</h3>
                <p class="text-body-secondary mb-4">Crea el primer almacén disponible en el sistema.</p>
                <a href="{{ route('warehouses.create') }}" class="btn btn-primary">
                    <i class="bi bi-building-add me-2" aria-hidden="true"></i>Crear almacén
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Usuarios asignados</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($warehouses as $warehouse)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="admin-user-avatar flex-shrink-0" aria-hidden="true"><i class="bi bi-building"></i></span>
                                        <strong class="fw-semibold">{{ $warehouse->name }}</strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="warehouse-badge">
                                        {{ $warehouse->users_count }} {{ $warehouse->users_count === 1 ? 'usuario' : 'usuarios' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @if (Auth::user()->canManageSuppliersIn($warehouse))
                                        <a href="{{ route('warehouses.suppliers.index', $warehouse) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver proveedores de {{ $warehouse->name }}">
                                            <i class="bi bi-truck me-1" aria-hidden="true"></i>Proveedores
                                        </a>
                                    @endif
                                    @if (Auth::user()->canManageWarehouse($warehouse))
                                        <a href="{{ route('warehouses.locations.index', $warehouse) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver ubicaciones de {{ $warehouse->name }}">
                                            <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Ubicaciones
                                        </a>
                                        <a href="{{ route('warehouses.cabinets.index', $warehouse) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver gabinetes de {{ $warehouse->name }}">
                                            <i class="bi bi-archive me-1" aria-hidden="true"></i>Gabinetes
                                        </a>
                                        <a href="{{ route('warehouses.inventory.index', $warehouse) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver inventario de {{ $warehouse->name }}">
                                            <i class="bi bi-clipboard-data me-1" aria-hidden="true"></i>Inventario
                                        </a>
                                        <a href="{{ route('warehouses.entries.index', $warehouse) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver entradas de {{ $warehouse->name }}">
                                            <i class="bi bi-receipt me-1" aria-hidden="true"></i>Entradas
                                        </a>
                                        <a href="{{ route('warehouses.transfers.index', $warehouse) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver transferencias de {{ $warehouse->name }}">
                                            <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>Transferencias
                                        </a>
                                    @endif
                                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $warehouse->name }}">
                                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
                                    </a>

                                    @if ($warehouse->users_count > 0)
                                        <span class="d-inline-block ms-1" title="No se puede eliminar porque tiene usuarios asignados.">
                                            <button type="button" class="btn btn-sm btn-outline-danger" disabled aria-label="No se puede eliminar {{ $warehouse->name }} porque tiene usuarios asignados">
                                                <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                            </button>
                                        </span>
                                    @else
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger ms-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteWarehouseModal"
                                            data-modal-name="{{ $warehouse->name }}"
                                            data-modal-name-target="#deleteWarehouseName"
                                            data-modal-delete-url="{{ route('warehouses.destroy', $warehouse) }}"
                                            data-modal-form-target="#deleteWarehouseForm"
                                        >
                                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($warehouses->hasPages())
                <div class="border-top px-3 py-3">
                    {{ $warehouses->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteWarehouseModal" tabindex="-1" aria-labelledby="deleteWarehouseModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="deleteWarehouseModalLabel">¿Eliminar almacén?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Esta acción eliminará el almacén y no se puede deshacer.</p>
                    <p class="fw-semibold mb-0" id="deleteWarehouseName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteWarehouseForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
