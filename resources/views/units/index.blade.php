@extends('layouts.app')

@section('page-title', 'Unidades de medida')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Unidades de medida</h2>
            <p>Administra las unidades disponibles para los productos.</p>
        </div>
        <a href="{{ route('units.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva unidad
        </a>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="unit-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="unit-list-title">Unidades registradas</h3>
            <p class="small text-body-secondary mb-0">{{ $units->total() }} {{ $units->total() === 1 ? 'unidad' : 'unidades' }}</p>
        </div>

        @if ($units->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-rulers"></i></span>
                <h3 class="h5">No hay unidades registradas.</h3>
                <p class="text-body-secondary mb-4">Crea la primera unidad de medida disponible.</p>
                <a href="{{ route('units.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear unidad
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Abreviatura</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr>
                                <td><strong class="fw-semibold">{{ $unit->name }}</strong></td>
                                <td>{{ $unit->abbreviation ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('units.edit', $unit) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $unit->name }}">
                                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
                                    </a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger ms-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteUnitModal"
                                        data-modal-name="{{ $unit->name }}"
                                        data-modal-name-target="#deleteUnitName"
                                        data-modal-delete-url="{{ route('units.destroy', $unit) }}"
                                        data-modal-form-target="#deleteUnitForm"
                                    >
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($units->hasPages())
                <div class="border-top px-3 py-3">
                    {{ $units->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteUnitModal" tabindex="-1" aria-labelledby="deleteUnitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="deleteUnitModalLabel">¿Eliminar unidad?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Esta acción eliminará la unidad y no se puede deshacer.</p>
                    <p class="fw-semibold mb-0" id="deleteUnitName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteUnitForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
