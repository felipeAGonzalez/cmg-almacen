@extends('layouts.app')

@section('page-title', 'Ubicaciones')

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
                    <li class="breadcrumb-item active" aria-current="page">Ubicaciones</li>
                </ol>
            </nav>
            <h2>Ubicaciones</h2>
            <p>Administra las ubicaciones físicas de este almacén.</p>
            <span class="badge text-bg-light border mt-2"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (Auth::user()->isAdmin())
                <a href="{{ route('warehouses.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a almacenes</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al inicio</a>
            @endif
            <a href="{{ route('warehouses.locations.create', $warehouse) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva ubicación</a>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm" role="note">
        <i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>Las ubicaciones son opcionales y ayudan a identificar dónde se encuentra físicamente el material dentro del almacén.
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="location-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="location-list-title">Ubicaciones registradas</h3>
            <p class="small text-body-secondary mb-0">{{ $locations->total() }} {{ $locations->total() === 1 ? 'ubicación' : 'ubicaciones' }}</p>
        </div>

        @if ($locations->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></span>
                <h3 class="h5">No hay ubicaciones registradas en este almacén.</h3>
                <p class="text-body-secondary mb-4">Puedes continuar usando el almacén sin registrar ubicaciones.</p>
                <a href="{{ route('warehouses.locations.create', $warehouse) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear ubicación</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead><tr><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col" class="text-end">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($locations as $location)
                            <tr>
                                <td><strong class="fw-semibold">{{ $location->name }}</strong></td>
                                <td class="text-wrap text-body-secondary">{{ $location->description ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('warehouses.locations.edit', [$warehouse, $location]) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $location->name }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#deleteLocationModal" data-modal-name="{{ $location->name }}" data-modal-name-target="#deleteLocationName" data-modal-delete-url="{{ route('warehouses.locations.destroy', [$warehouse, $location]) }}" data-modal-form-target="#deleteLocationForm"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($locations->hasPages())
                <div class="border-top px-3 py-3">{{ $locations->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteLocationModal" tabindex="-1" aria-labelledby="deleteLocationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
            <div class="modal-header"><h2 class="modal-title fs-5" id="deleteLocationModalLabel">¿Eliminar ubicación?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="mb-2">Esta acción eliminará la ubicación y no se puede deshacer.</p><p class="fw-semibold mb-0" id="deleteLocationName"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="deleteLocationForm">@csrf @method('DELETE')<button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button></form>
            </div>
        </div></div>
    </div>
@endsection
