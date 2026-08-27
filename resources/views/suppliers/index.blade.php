@extends('layouts.app')

@section('page-title', 'Proveedores')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación">
                <ol class="breadcrumb small mb-2">
                    @if (Auth::user()->isAdmin())
                        <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                    @else
                        <li class="breadcrumb-item">Mis almacenes</li>
                    @endif
                    <li class="breadcrumb-item">{{ $warehouse->name }}</li>
                    <li class="breadcrumb-item active" aria-current="page">Proveedores</li>
                </ol>
            </nav>
            <h2>Proveedores</h2>
            <p>Administra los proveedores registrados en este almacén.</p>
            <span class="badge text-bg-light border mt-2"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (Auth::user()->isAdmin())
                <a href="{{ route('warehouses.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver a almacenes</a>
            @else
                <a href="{{ route('home') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Volver al inicio</a>
            @endif
            <a href="{{ route('warehouses.suppliers.create', $warehouse) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo proveedor</a>
        </div>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="supplier-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="supplier-list-title">Proveedores registrados</h3>
            <p class="small text-body-secondary mb-0">{{ $suppliers->total() }} {{ $suppliers->total() === 1 ? 'proveedor' : 'proveedores' }}</p>
        </div>

        @if ($suppliers->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-truck"></i></span>
                <h3 class="h5">No hay proveedores registrados en este almacén.</h3>
                <p class="text-body-secondary mb-4">Agrega el primer proveedor para este almacén.</p>
                <a href="{{ route('warehouses.suppliers.create', $warehouse) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear proveedor</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nombre</th>
                            <th scope="col">Contacto</th>
                            <th scope="col">Teléfono</th>
                            <th scope="col">Correo electrónico</th>
                            <th scope="col" class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($suppliers as $supplier)
                            <tr>
                                <td><strong class="fw-semibold">{{ $supplier->name }}</strong></td>
                                <td>{{ $supplier->contact_name ?: '—' }}</td>
                                <td>
                                    @if ($supplier->phone)
                                        <a href="tel:{{ $supplier->phone }}" class="text-decoration-none">{{ $supplier->phone }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($supplier->email)
                                        <a href="mailto:{{ $supplier->email }}" class="text-decoration-none">{{ $supplier->email }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('warehouses.suppliers.edit', [$warehouse, $supplier]) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $supplier->name }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar</a>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger ms-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteSupplierModal"
                                        data-modal-name="{{ $supplier->name }}"
                                        data-modal-name-target="#deleteSupplierName"
                                        data-modal-delete-url="{{ route('warehouses.suppliers.destroy', [$warehouse, $supplier]) }}"
                                        data-modal-form-target="#deleteSupplierForm"
                                    ><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="border-top px-3 py-3">{{ $suppliers->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        @endif
    </section>

    <div class="modal fade" id="deleteSupplierModal" tabindex="-1" aria-labelledby="deleteSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="deleteSupplierModalLabel">¿Eliminar proveedor?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Esta acción eliminará el proveedor y no se puede deshacer.</p>
                    <p class="fw-semibold mb-0" id="deleteSupplierName"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" id="deleteSupplierForm">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
