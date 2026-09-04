@extends('layouts.app')

@section('page-title', 'Crear vale de Enfermería')

@section('content')
<div class="page-heading">
    <div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
            <li class="breadcrumb-item"><a href="{{ route('nursing-vouchers.index') }}">Vales de Enfermería</a></li>
            <li class="breadcrumb-item active" aria-current="page">Crear vale</li>
        </ol></nav>
        <h2>Crear vale de Enfermería</h2>
        <p>Selecciona los insumos necesarios para la hospitalización activa.</p>
    </div>
</div>

@if(isset($creationError))
    <div class="alert alert-danger" role="alert">
        <h3 class="h6 fw-bold"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>No es posible crear el vale</h3>
        <p class="mb-0">{{ $creationError }}</p>
    </div>
    <a href="{{ route('nursing.hospital-context') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-2"></i>Volver al contexto hospitalario</a>
@else
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <section class="admin-card h-100 p-4">
                <span class="entry-detail-label">Paciente</span>
                <h3 class="h5 mb-2">{{ $hospitalization->patientName }}</h3>
                <p class="mb-0"><i class="bi bi-door-open me-2"></i>Habitación {{ $hospitalization->roomNumber }}</p>
            </section>
        </div>
        <div class="col-md-6">
            <section class="admin-card h-100 p-4">
                <span class="entry-detail-label">Origen de surtido</span>
                @if($source->type === \App\Enums\NursingSupplySourceType::WAREHOUSE)
                    <h3 class="h5 mb-2"><span class="badge text-bg-primary">Almacén</span> {{ $source->warehouse->name }}</h3>
                    <p class="mb-0">El vale será enviado al almacén para su surtido.</p>
                @else
                    <h3 class="h5 mb-2"><span class="badge text-bg-info">Gabinete</span> {{ $source->cabinet->name }}</h3>
                    <p class="mb-0">El almacén se encuentra fuera de horario o en día de descanso. El vale se surtirá desde el gabinete.</p>
                @endif
            </section>
        </div>
    </div>

    @php
        $rows = old('items', [['product_id' => '', 'quantity' => '']]);
        $productOptions = $inventoryItems->map(fn($inventoryItem) => [
            'id' => $inventoryItem->product->id,
            'name' => $inventoryItem->product->name,
            'unit' => $inventoryItem->product->unit->abbreviation ?: $inventoryItem->product->unit->name,
            'code' => $inventoryItem->product->code,
            'barcode' => $inventoryItem->product->barcode,
        ])->values();
    @endphp
    <form method="POST" action="{{ route('nursing-vouchers.store') }}" data-nursing-voucher-form>
        @csrf
        <section class="admin-card mb-4" data-nursing-voucher-items data-product-options='@json($productOptions)'>
            <div class="admin-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div><h3 class="h6 fw-bold mb-1">Productos solicitados</h3><p class="small text-body-secondary mb-0">Puedes buscar por nombre, código o código de barras.</p></div>
                <button type="button" class="btn btn-outline-primary btn-sm" data-add-voucher-item><i class="bi bi-plus-lg me-1"></i>Agregar producto</button>
            </div>
            <div class="p-3 d-grid gap-3" data-voucher-items-list>
                @foreach($rows as $index => $row)
                    @include('nursing-vouchers._item', ['index' => $index, 'row' => $row, 'inventoryItems' => $inventoryItems])
                @endforeach
            </div>
        </section>
        @error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror

        <section class="admin-card p-4 mb-4">
            <label for="notes" class="form-label fw-semibold">Notas <span class="text-body-secondary fw-normal">(Opcional)</span></label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000" class="form-control @error('notes') is-invalid @enderror" placeholder="Agrega información operativa necesaria para el surtido.">{{ old('notes') }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </section>

        <div class="d-flex flex-wrap justify-content-end gap-2">
            <a href="{{ route('nursing.hospital-context') }}" class="btn btn-light border">Cancelar</a>
            <button type="submit" class="btn btn-primary" data-voucher-submit><i class="bi bi-check2-circle me-2"></i>Crear vale</button>
        </div>
    </form>

    <template data-nursing-voucher-item-template>
        @include('nursing-vouchers._item', ['index' => '__INDEX__', 'row' => ['product_id' => '', 'quantity' => ''], 'inventoryItems' => $inventoryItems])
    </template>
@endif
@endsection
