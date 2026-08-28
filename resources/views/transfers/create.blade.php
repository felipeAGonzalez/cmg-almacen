@extends('layouts.app')

@section('page-title', 'Nueva transferencia')

@php
    $submittedItems = old('items', [['source_inventory_item_id' => '', 'quantity' => '']]);
    $frontendData = [
        'cabinets' => $cabinets->mapWithKeys(fn($cabinet) => [(string) $cabinet->id => $cabinet->inventoryItems->pluck('product_id')->map(fn($id) => (string) $id)->values()]),
        'inventoryItems' => $inventoryItems->map(fn($inventoryItem) => [
            'id' => (string) $inventoryItem->id,
            'productId' => (string) $inventoryItem->product_id,
            'name' => $inventoryItem->product->name,
            'unit' => $inventoryItem->product->unit->name,
            'identifier' => $inventoryItem->product->code ?: $inventoryItem->product->barcode,
            'usableStock' => (string) ($inventoryItem->usable_stock ?? '0.000'),
        ])->values(),
    ];
@endphp

@section('content')
    <div class="page-heading"><div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
            @if(Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif
            <li class="breadcrumb-item">{{ $warehouse->name }}</li><li class="breadcrumb-item"><a href="{{ route('warehouses.transfers.index', $warehouse) }}">Transferencias</a></li><li class="breadcrumb-item active">Nueva</li>
        </ol></nav>
        <h2>Nueva transferencia</h2><p>Selecciona el gabinete y los productos que serán surtidos desde el almacén.</p>
        <span class="badge text-bg-light border"><i class="bi bi-building me-1"></i>Almacén: {{ $warehouse->name }}</span>
    </div></div>

    @if($cabinets->isEmpty() || $inventoryItems->isEmpty())
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Debes contar con al menos un gabinete y productos configurados en el inventario principal antes de registrar una transferencia.</div>
    @endif

    <form method="POST" action="{{ route('warehouses.transfers.store', $warehouse) }}" data-transfer-form novalidate>
        @csrf
        <section class="admin-card mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Datos de la transferencia</h3></div><div class="p-4"><div class="row g-3">
            <div class="col-lg-5"><label for="cabinet_id" class="form-label">Gabinete destino <span class="text-danger">*</span></label><select id="cabinet_id" name="cabinet_id" class="form-select @error('cabinet_id') is-invalid @enderror" data-transfer-cabinet required><option value="">Selecciona un gabinete</option>@foreach($cabinets as $cabinet)<option value="{{ $cabinet->id }}" @selected((string) old('cabinet_id') === (string) $cabinet->id)>{{ $cabinet->name }}</option>@endforeach</select>@error('cabinet_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-lg-7"><label for="notes" class="form-label">Notas <span class="text-body-secondary fw-normal">(Opcional)</span></label><textarea id="notes" name="notes" rows="2" maxlength="2000" class="form-control @error('notes') is-invalid @enderror" placeholder="Ej. Reposición de material de uso frecuente">{{ old('notes') }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        </div></div></section>

        <section class="admin-card mb-4" data-transfer-items data-transfer-data='@json($frontendData)'>
            <div class="admin-card-header d-flex flex-wrap justify-content-between gap-2 align-items-center"><div><h3 class="h6 fw-bold mb-1">Productos a transferir</h3><p class="small text-body-secondary mb-0">El sistema seleccionará automáticamente los lotes disponibles, priorizando los que caduquen primero.</p></div><button type="button" class="btn btn-sm btn-outline-primary" data-add-transfer-item><i class="bi bi-plus-circle me-1"></i>Agregar producto</button></div>
            <div class="p-3 p-lg-4">
                @error('items')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                <div class="alert alert-warning py-2 d-none" data-transfer-compatibility-warning>Se limpiaron productos que no están configurados en el nuevo gabinete.</div>
                <div data-transfer-items-list>@foreach($submittedItems as $index => $submittedItem)@include('transfers._item', ['index' => $index, 'item' => $submittedItem, 'inventoryItems' => $inventoryItems])@endforeach</div>
            </div>
        </section>
        <div class="alert alert-light border small"><i class="bi bi-info-circle me-2"></i>Una vez registrada, la transferencia quedará como historial y no podrá editarse ni eliminarse desde este módulo.</div>
        <div class="form-actions"><a href="{{ route('warehouses.transfers.index', $warehouse) }}" class="btn btn-light border">Cancelar</a><button type="submit" class="btn btn-primary" data-transfer-submit @disabled($cabinets->isEmpty() || $inventoryItems->isEmpty())><i class="bi bi-check-circle me-2"></i>Registrar transferencia</button></div>
    </form>
    <template data-transfer-item-template>@include('transfers._item', ['index' => '__INDEX__', 'item' => [], 'inventoryItems' => $inventoryItems])</template>
@endsection
