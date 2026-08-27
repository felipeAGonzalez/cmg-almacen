@extends('layouts.app')
@section('page-title', 'Editar producto')
@section('content')
    <div class="page-heading"><div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ route('products.index') }}">Productos</a></li><li class="breadcrumb-item active" aria-current="page">Editar producto</li></ol></nav>
        <h2>Editar producto</h2><p>Actualiza la información del producto.</p>
    </div></div>
    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="edit-product-title">
        <h3 class="visually-hidden" id="edit-product-title">Formulario para editar producto</h3>
        @include('products._form')
    </section>
@endsection
