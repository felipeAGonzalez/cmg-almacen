@extends('layouts.app')

@section('page-title', 'Categorías')

@section('content')
    <div class="page-heading">
        <div>
            <h2>Categorías</h2>
            <p>Administra las categorías disponibles para los productos.</p>
        </div>
        <a href="{{ route('categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva categoría
        </a>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="category-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="category-list-title">Categorías registradas</h3>
            <p class="small text-body-secondary mb-0">{{ $categories->total() }} {{ $categories->total() === 1 ? 'categoría' : 'categorías' }}</p>
        </div>

        @if ($categories->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-tags"></i></span>
                <h3 class="h5">No hay categorías registradas.</h3>
                <p class="text-body-secondary mb-4">Crea la primera categoría disponible para los productos.</p>
                <a href="{{ route('categories.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear categoría
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
                        @foreach ($categories as $category)
                            <tr>
                                <td><strong class="fw-semibold">{{ $category->name }}</strong></td>
                                <td class="text-wrap text-body-secondary">{{ $category->description ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $category->name }}">
                                        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar
                                    </a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger ms-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteCategoryModal"
                                        data-modal-name="{{ $category->name }}"
                                        data-modal-name-target="#deleteCategoryName"
                                        data-modal-delete-url="{{ route('categories.destroy', $category) }}"
                                        data-modal-form-target="#deleteCategoryForm"
                                    >
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($categories->hasPages())
                <div class="border-top px-3 py-3">
                    {{ $categories->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="deleteCategoryModalLabel">¿Eliminar categoría?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Esta acción eliminará la categoría y no se puede deshacer.</p>
                    <p class="fw-semibold mb-0" id="deleteCategoryName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteCategoryForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
