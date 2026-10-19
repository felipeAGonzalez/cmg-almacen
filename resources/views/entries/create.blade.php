@extends('layouts.app')

@section('page-title', 'Registrar entrada')

@php
    $submittedItems = old('items', [[
        'inventory_item_id' => '',
        'quantity' => '',
        'total_cost' => '',
        'unit_cost' => '',
        'manufacturer_lot' => '',
        'expiration_date' => '',
    ]]);
    $inventoryOptions = $inventoryItems->map(fn ($inventoryItem) => [
        'id' => $inventoryItem->id,
        'name' => $inventoryItem->product->name,
        'unit' => $inventoryItem->product->unit->name,
        'identifier' => $inventoryItem->product->code ?: $inventoryItem->product->barcode,
        'requiresExpiration' => $inventoryItem->product->requires_expiration,
        'isVatTaxable' => $inventoryItem->product->isVatTaxable(),
    ])->values();
    $selectedSupplier = $suppliers->firstWhere('id', (int) old('supplier_id'));
@endphp

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
                @if (Auth::user()->isAdmin())
                    <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                @else
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                @endif
                <li class="breadcrumb-item">{{ $warehouse->name }}</li>
                <li class="breadcrumb-item"><a href="{{ route('warehouses.entries.index', $warehouse) }}">Entradas</a></li>
                <li class="breadcrumb-item active" aria-current="page">Registrar</li>
            </ol></nav>
            <h2>Registrar entrada</h2>
            <p>Captura los datos de la factura y los productos recibidos.</p>
            <span class="badge text-bg-light border"><i class="bi bi-building me-1" aria-hidden="true"></i>Almacén: {{ $warehouse->name }}</span>
        </div>
    </div>

    @if ($suppliers->isEmpty() || $inventoryItems->isEmpty())
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>
            @if ($suppliers->isEmpty() && $inventoryItems->isEmpty())
                Debes registrar un proveedor y configurar al menos un producto en el inventario antes de registrar una entrada.
            @elseif ($suppliers->isEmpty())
                Debes registrar al menos un proveedor en este almacén antes de registrar una entrada.
            @else
                Debes configurar al menos un producto en el inventario de este almacén antes de registrar una entrada.
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('warehouses.entries.store', $warehouse) }}" data-entry-form novalidate>
        @csrf
        <section class="admin-card mb-4">
            <div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Datos de la factura</h3></div>
            <div class="p-4"><div class="row g-3">
                <div class="col-lg-4">
                    <label for="supplier_id" class="form-label">Proveedor <span class="text-danger">*</span></label>
                    <select id="supplier_id" name="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" data-entry-supplier required>
                        <option value="">Selecciona un proveedor</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" data-charges-freight="{{ $supplier->charges_freight ? '1' : '0' }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}{{ $supplier->charges_freight ? ' — Cobra flete' : '' }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label for="invoice_number" class="form-label">Número de factura <span class="text-danger">*</span></label>
                    <input id="invoice_number" name="invoice_number" value="{{ old('invoice_number') }}" class="form-control @error('invoice_number') is-invalid @enderror" placeholder="Ej. A-12345" maxlength="255" required>
                    @error('invoice_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4 {{ $selectedSupplier?->charges_freight ? '' : 'd-none' }}" data-entry-freight-container>
                    <label for="freight_cost" class="form-label">Costo de flete <span class="text-danger">*</span></label>
                    <div class="input-group has-validation">
                        <span class="input-group-text">$</span>
                        <input id="freight_cost" name="freight_cost" type="number" step="0.01" min="0.01" value="{{ old('freight_cost') }}" class="form-control @error('freight_cost') is-invalid @enderror" data-entry-freight-cost @required($selectedSupplier?->charges_freight) @disabled(! $selectedSupplier?->charges_freight)>
                        @error('freight_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-text">Este importe se suma al total de la factura y causa IVA del 16%.</div>
                </div>
                <div class="col-lg-4">
                    <label for="invoice_date" class="form-label">Fecha de factura <span class="text-danger">*</span></label>
                    <input id="invoice_date" name="invoice_date" type="date" value="{{ old('invoice_date') }}" class="form-control @error('invoice_date') is-invalid @enderror" required>
                    @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">Notas <span class="text-body-secondary fw-normal">(Opcional)</span></label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div></div>
        </section>

        <section class="admin-card mb-4" data-entry-items data-inventory-options='@json($inventoryOptions)'>
            <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><h3 class="h6 fw-bold mb-1">Productos recibidos</h3><p class="small text-body-secondary mb-0">Cada partida generará un lote interno de inventario.</p></div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-entry-item><i class="bi bi-plus-circle me-1"></i>Agregar producto</button>
            </div>
            <div class="p-3 p-lg-4">
                @error('items')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
                <div class="entry-items" data-entry-items-list>
                    @foreach ($submittedItems as $index => $submittedItem)
                        @include('entries._item', ['index' => $index, 'item' => $submittedItem, 'inventoryItems' => $inventoryItems])
                    @endforeach
                </div>
                <div class="entry-total flex-column align-items-stretch gap-2 mt-3">
                    <div class="d-flex justify-content-between gap-3"><span>Subtotal</span><strong data-entry-subtotal-total>$0.00</strong></div>
                    <div class="d-flex justify-content-between gap-3"><span>IVA aplicable (16%)</span><strong data-entry-vat>$0.00</strong></div>
                    <div class="d-flex justify-content-between gap-3"><span>Flete</span><strong data-entry-freight-total>$0.00</strong></div>
                    <div class="d-flex justify-content-between gap-3 border-top pt-2"><span>Total de la factura</span><strong data-entry-total>$0.00</strong></div>
                </div>
            </div>
        </section>

        <div class="alert alert-light border small" role="note"><i class="bi bi-info-circle me-2"></i>Una vez registrada, la entrada quedará como historial y no podrá editarse ni eliminarse desde este módulo.</div>
        <div class="form-actions">
            <a href="{{ route('warehouses.entries.index', $warehouse) }}" class="btn btn-light border">Cancelar</a>
            <button type="submit" class="btn btn-primary" data-entry-submit @disabled($suppliers->isEmpty() || $inventoryItems->isEmpty())><i class="bi bi-check-circle me-2"></i>Registrar entrada</button>
        </div>
    </form>

    <template data-entry-item-template>
        @include('entries._item', ['index' => '__INDEX__', 'item' => [], 'inventoryItems' => $inventoryItems])
    </template>
@endsection
