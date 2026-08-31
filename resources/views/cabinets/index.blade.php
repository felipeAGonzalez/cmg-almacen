@extends('layouts.app')

@section('page-title', 'Gabinetes')

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
                    <li class="breadcrumb-item active" aria-current="page">Gabinetes</li>
                </ol>
            </nav>
            <h2>Gabinetes</h2>
            <p>Administra los gabinetes disponibles en este almacén.</p>
            <span class="badge text-bg-light border mt-2"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (Auth::user()->isAdmin())
                <a href="{{ route('warehouses.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a almacenes</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al inicio</a>
            @endif
            <a href="{{ route('warehouses.cabinets.create', $warehouse) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo gabinete</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm" role="note">
        <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>Los gabinetes son puntos de resguardo independientes del almacén y posteriormente tendrán existencias propias.
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="cabinet-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="cabinet-list-title">Gabinetes registrados</h3>
            <p class="small text-body-secondary mb-0">{{ $cabinets->total() }} {{ $cabinets->total() === 1 ? 'gabinete' : 'gabinetes' }}</p>
        </div>

        @if ($cabinets->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-archive"></i></span>
                <h3 class="h5">No hay gabinetes registrados en este almacén.</h3>
                <p class="text-body-secondary mb-4">El almacén puede funcionar sin gabinetes.</p>
                <a href="{{ route('warehouses.cabinets.create', $warehouse) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear gabinete</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead><tr><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col" class="text-end">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($cabinets as $cabinet)
                            <tr>
                                <td><strong class="fw-semibold">{{ $cabinet->name }}</strong></td>
                                <td class="text-wrap text-body-secondary">{{ $cabinet->description ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver inventario de {{ $cabinet->name }}"><i class="bi bi-clipboard-data me-1" aria-hidden="true"></i>Inventario</a>
                                    <a href="{{ route('warehouses.cabinets.kardex.index', [$warehouse, $cabinet]) }}" class="btn btn-sm btn-outline-secondary" aria-label="Ver Kardex de {{ $cabinet->name }}"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Kardex</a>
                                    <a href="{{ route('warehouses.cabinets.edit', [$warehouse, $cabinet]) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $cabinet->name }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#deleteCabinetModal" data-modal-name="{{ $cabinet->name }}" data-modal-name-target="#deleteCabinetName" data-modal-delete-url="{{ route('warehouses.cabinets.destroy', [$warehouse, $cabinet]) }}" data-modal-form-target="#deleteCabinetForm"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($cabinets->hasPages())
                <div class="border-top px-3 py-3">{{ $cabinets->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteCabinetModal" tabindex="-1" aria-labelledby="deleteCabinetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
            <div class="modal-header"><h2 class="modal-title fs-5" id="deleteCabinetModalLabel">¿Eliminar gabinete?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="mb-2">Esta acción eliminará el gabinete y no se puede deshacer.</p><p class="fw-semibold mb-0" id="deleteCabinetName"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="deleteCabinetForm">@csrf @method('DELETE')<button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button></form>
            </div>
        </div></div>
    </div>
@endsection
