@extends('layouts.app')
@section('page-title', 'Nuevo producto')
@section('content')
    <div class="page-heading"><div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ route('products.index') }}">Productos</a></li><li class="breadcrumb-item active" aria-current="page">Nuevo producto</li></ol></nav>
        <h2>Nuevo producto</h2><p>Captura la información del producto.</p>
    </div></div>
    <section class="admin-card admin-form-card mx-auto p-3 p-md-4" aria-labelledby="create-product-title">
        <h3 class="visually-hidden" id="create-product-title">Formulario para crear producto</h3>
        @include('products._form')
    </section>
@endsection
