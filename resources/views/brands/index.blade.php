@extends('layouts.app')

@section('page-title', 'Marcas')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Marcas</h2>
            <p>Administra las marcas disponibles para los productos.</p>
        </div>
        <a href="{{ route('brands.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva marca
        </a>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="brand-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="brand-list-title">Marcas registradas</h3>
            <p class="small text-body-secondary mb-0">{{ $brands->total() }} {{ $brands->total() === 1 ? 'marca' : 'marcas' }}</p>
        </div>

        @if ($brands->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-award"></i></span>
                <h3 class="h5">No hay marcas registradas.</h3>
                <p class="text-body-secondary mb-4">Crea la primera marca disponible para los productos.</p>
                <a href="{{ route('brands.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear marca
                </a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Descripción</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($brands as $brand)
                            <tr>
                                <td><strong class="fw-semibold">{{ $brand->name }}</strong></td>
                                <td class="text-wrap text-body-secondary">{{ $brand->description ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('brands.edit', $brand) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $brand->name }}">
                                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
                                    </a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger ms-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteBrandModal"
                                        data-modal-name="{{ $brand->name }}"
                                        data-modal-name-target="#deleteBrandName"
                                        data-modal-delete-url="{{ route('brands.destroy', $brand) }}"
                                        data-modal-form-target="#deleteBrandForm"
                                    >
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($brands->hasPages())
                <div class="border-top px-3 py-3">
                    {{ $brands->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteBrandModal" tabindex="-1" aria-labelledby="deleteBrandModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="deleteBrandModalLabel">¿Eliminar marca?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Esta acción eliminará la marca y no se puede deshacer.</p>
                    <p class="fw-semibold mb-0" id="deleteBrandName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteBrandForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
