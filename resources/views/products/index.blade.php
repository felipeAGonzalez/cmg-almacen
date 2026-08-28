@extends('layouts.app')
@section('page-title', 'Productos')
@section('content')
    <div class="page-heading">
        <div><h2>Productos</h2><p>Administra el catálogo general de productos.</p></div>
        <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nuevo producto</a>
    </div>
    <section class="admin-card overflow-hidden" aria-labelledby="product-list-title">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1" id="product-list-title">Productos registrados</h3>
            <p class="small text-body-secondary mb-0">{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }}</p>
        </div>
        @if ($products->isEmpty())
            <div class="empty-state">
                <span class="empty-state-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
                <h3 class="h5">No hay productos registrados.</h3>
                <p class="text-body-secondary mb-4">Crea el primer producto del catálogo general.</p>
                <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear producto</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead><tr><th scope="col">Producto</th><th scope="col">Código</th><th scope="col">Unidad</th><th scope="col">Categoría</th><th scope="col">Marca</th><th scope="col">Caducidad</th><th scope="col" class="text-end">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($products as $product)
                            <tr>
                                <td>
                                    <strong class="fw-semibold d-block">{{ $product->name }}</strong>
                                    @if ($product->description)<span class="small text-body-secondary d-block text-wrap">{{ $product->description }}</span>@endif
                                </td>
                                <td class="small text-nowrap">
                                    @if ($product->code)<span class="d-block"><span class="text-body-secondary">Interno:</span> {{ $product->code }}</span>@endif
                                    @if ($product->barcode)<span class="d-block"><span class="text-body-secondary">Barras:</span> {{ $product->barcode }}</span>@endif
                                </td>
                                <td>{{ $product->unit->name }}</td>
                                <td>{{ $product->category->name }}</td>
                                <td>{{ $product->brand->name }}</td>
                                <td><span class="badge {{ $product->requires_expiration ? 'text-bg-warning' : 'text-bg-light border' }}">{{ $product->requires_expiration ? 'Sí' : 'No' }}</span></td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary" aria-label="Editar {{ $product->name }}"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Editar</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="modal" data-bs-target="#deleteProductModal" data-modal-name="{{ $product->name }}" data-modal-name-target="#deleteProductName" data-modal-delete-url="{{ route('products.destroy', $product) }}" data-modal-form-target="#deleteProductForm"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())<div class="border-top px-3 py-3">{{ $products->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>
    <div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
            <div class="modal-header"><h2 class="modal-title fs-5" id="deleteProductModalLabel">¿Eliminar producto?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><p class="mb-2">Esta acción eliminará el producto y no se puede deshacer.</p><p class="fw-semibold mb-0" id="deleteProductName"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" id="deleteProductForm">@csrf @method('DELETE')<button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1" aria-hidden="true"></i>Eliminar</button></form>
            </div>
        </div></div>
    </div>
@endsection
