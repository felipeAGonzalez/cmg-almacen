@extends('layouts.app')
@section('page-title', 'Registrar salida')
@php
    $submittedItems = old('items', [['inventory_item_id' => '', 'quantity' => '']]);
    $selectedSourceKey = old('source_cabinet_id') ? 'cabinet:'.old('source_cabinet_id') : 'warehouse';
    $inventoryOptions = $inventoryItems->map(fn($inventoryItem) => ['id' => (string) $inventoryItem->id, 'name' => $inventoryItem->product->name, 'unit' => $inventoryItem->product->unit->name, 'identifier' => $inventoryItem->product->code ?: $inventoryItem->product->barcode, 'usableStock' => (string) ($inventoryItem->usable_stock ?? '0.000'), 'sourceKey' => $inventoryItem->stockable_type === 'warehouse' ? 'warehouse' : 'cabinet:'.$inventoryItem->stockable_id])->values();
@endphp
@section('content')
<div class="page-heading"><div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">@if(Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif<li class="breadcrumb-item">{{ $warehouse->name }}</li><li class="breadcrumb-item"><a href="{{ route('warehouses.outbounds.index', $warehouse) }}">Salidas</a></li><li class="breadcrumb-item active">Nueva</li></ol></nav><h2>Registrar salida</h2><p>Captura una salida general o un vale manual destinado a un paciente.</p><span class="badge text-bg-light border"><i class="bi bi-building me-1"></i>Almacén: {{ $warehouse->name }}</span></div></div>
@if($inventoryItems->isEmpty())<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Debes configurar productos con existencia utilizable en el inventario principal antes de registrar una salida.</div>@endif
<form method="POST" action="{{ route('warehouses.outbounds.store', $warehouse) }}" data-outbound-form data-patient-care-reason="{{ \App\Enums\InventoryOutboundReason::PATIENT_CARE->value }}" novalidate>@csrf
<section class="admin-card mb-4">
    <div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Datos de la salida</h3></div>
    <div class="p-4">
        <div class="row g-3">
            @if(Auth::user()->isAdmin())
            <div class="col-12">
                <label for="source_cabinet_id" class="form-label">Origen del inventario <span class="text-danger">*</span></label>
                <select id="source_cabinet_id" name="source_cabinet_id" class="form-select @error('source_cabinet_id') is-invalid @enderror" data-outbound-source>
                    <option value="" @selected(!old('source_cabinet_id'))>Almacén — {{ $warehouse->name }}</option>
                    @foreach($cabinets as $cabinet)
                        <option value="{{ $cabinet->id }}" @selected((string) old('source_cabinet_id') === (string) $cabinet->id)>Gabinete — {{ $cabinet->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">La existencia se descontará únicamente del origen seleccionado.</div>
                @error('source_cabinet_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="alert alert-warning py-2 mt-2 mb-0 d-none" data-outbound-source-warning>Se limpiaron productos porque no pertenecen al nuevo origen.</div>
            </div>
            @else
            <div class="col-12">
                <span class="entry-detail-label">Origen del inventario</span>
                <strong class="d-block">Almacén — {{ $warehouse->name }}</strong>
            </div>
            @endif
            <div class="col-lg-6">
                <label for="reason" class="form-label">Motivo <span class="text-danger">*</span></label>
                <select id="reason" name="reason" class="form-select @error('reason') is-invalid @enderror" data-outbound-reason required>
                    <option value="">Selecciona un motivo</option>
                    @foreach($reasons as $reason)<option value="{{ $reason->value }}" @selected(old('reason', request('reason')) === $reason->value)>{{ $reason->label() }}</option>@endforeach
                </select>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-lg-6">
                <label for="processed_at" class="form-label">Fecha y hora de salida <span class="text-danger">*</span></label>
                <input id="processed_at" name="processed_at" type="datetime-local" value="{{ old('processed_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="form-control @error('processed_at') is-invalid @enderror" required>
                <div class="form-text">Puedes indicar una fecha anterior cuando captures un vale o una salida recibida en papel.</div>
                @error('processed_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="notes" class="form-label">Comentario <span class="text-body-secondary fw-normal">(Opcional)</span></label>
                <textarea id="notes" name="notes" rows="2" maxlength="2000" class="form-control @error('notes') is-invalid @enderror" placeholder="Agrega información adicional sobre la salida si es necesario.">{{ old('notes') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</section>
<section class="admin-card mb-4 {{ old('reason', request('reason')) === \App\Enums\InventoryOutboundReason::PATIENT_CARE->value ? '' : 'd-none' }}" data-patient-care-fields aria-live="polite">
    <div class="admin-card-header">
        <h3 class="h6 fw-bold mb-1">Destino del vale manual</h3>
        <p class="small text-body-secondary mb-0">Estos datos explican para quién se entregaron los insumos. Se capturan manualmente y no se validan con Hospitalización.</p>
    </div>
    <div class="p-4">
        <div class="row g-3">
            <div class="col-lg-6">
                <label for="patient_name" class="form-label">Paciente <span class="text-danger">*</span></label>
                <input id="patient_name" name="patient_name" type="text" maxlength="255" value="{{ old('patient_name') }}" class="form-control @error('patient_name') is-invalid @enderror" data-patient-care-required autocomplete="off">
                @error('patient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="room_number" class="form-label">Habitación <span class="text-body-secondary fw-normal">(Opcional)</span></label>
                <input id="room_number" name="room_number" type="text" maxlength="50" value="{{ old('room_number') }}" class="form-control @error('room_number') is-invalid @enderror" autocomplete="off">
                @error('room_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="requesting_area" class="form-label">Área solicitante <span class="text-body-secondary fw-normal">(Opcional)</span></label>
                <input id="requesting_area" name="requesting_area" type="text" maxlength="150" value="{{ old('requesting_area') }}" class="form-control @error('requesting_area') is-invalid @enderror" autocomplete="off">
                @error('requesting_area')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</section>
<section class="admin-card mb-4" data-outbound-items data-selected-source="{{ $selectedSourceKey }}" data-inventory-options='@json($inventoryOptions)'><div class="admin-card-header d-flex flex-wrap justify-content-between gap-2 align-items-center"><div><h3 class="h6 fw-bold mb-1">Productos a retirar</h3><p class="small text-body-secondary mb-0">El sistema mostrará sólo productos del origen elegido y seleccionará automáticamente los lotes disponibles mediante FEFO/FIFO.</p></div><button type="button" class="btn btn-sm btn-outline-primary" data-add-outbound-item><i class="bi bi-plus-circle me-1"></i>Agregar producto</button></div><div class="p-3 p-lg-4"><div class="alert alert-warning py-2 d-none" data-outbound-empty-source>No hay productos configurados en el origen seleccionado.</div>@error('items')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror<div data-outbound-items-list>@foreach($submittedItems as $index => $submittedItem)@include('outbounds._item', ['index' => $index, 'item' => $submittedItem, 'inventoryItems' => $inventoryItems])@endforeach</div></div></section>
<div class="alert alert-light border small"><i class="bi bi-info-circle me-2"></i>Una vez registrada, la salida quedará como historial y no podrá editarse ni eliminarse desde este módulo.</div><div class="form-actions"><a href="{{ route('warehouses.outbounds.index', $warehouse) }}" class="btn btn-light border">Cancelar</a><button type="submit" class="btn btn-primary" data-outbound-submit @disabled($inventoryItems->isEmpty())><i class="bi bi-check-circle me-2"></i>Registrar salida</button></div></form>
<template data-outbound-item-template>@include('outbounds._item', ['index' => '__INDEX__', 'item' => [], 'inventoryItems' => $inventoryItems])</template>
@endsection
