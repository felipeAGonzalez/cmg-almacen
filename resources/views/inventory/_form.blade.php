@php($isEditing = isset($inventoryItem))
@php($isCabinet = isset($cabinet))
@php($formAction = $isCabinet
    ? ($isEditing ? route('warehouses.cabinets.inventory.update', [$warehouse, $cabinet, $inventoryItem]) : route('warehouses.cabinets.inventory.store', [$warehouse, $cabinet]))
    : ($isEditing ? route('warehouses.inventory.update', [$warehouse, $inventoryItem]) : route('warehouses.inventory.store', $warehouse)))
@php($cancelRoute = $isCabinet ? route('warehouses.cabinets.inventory.index', [$warehouse, $cabinet]) : route('warehouses.inventory.index', $warehouse))

<form method="POST" action="{{ $formAction }}" novalidate>
    @csrf
    @if ($isEditing) @method('PUT') @endif

    <div class="form-section">
        <p class="small text-body-secondary mb-4">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

        @if ($isEditing)
            <label class="form-label">Producto</label>
            <div class="rounded border bg-light p-3 mb-3">
                <strong class="d-block">{{ $inventoryItem->product->name }}</strong>
                <span class="small text-body-secondary">
                    @if ($inventoryItem->product->code) Código interno: {{ $inventoryItem->product->code }} @endif
                    @if ($inventoryItem->product->barcode) Código de barras: {{ $inventoryItem->product->barcode }} @endif
                </span>
            </div>
            <input type="hidden" name="product_id" value="{{ $inventoryItem->product_id }}">
            <div class="form-text mb-3">El producto no puede cambiarse desde esta pantalla.</div>
        @else
            <div class="mb-3">
                <label for="product_id" class="form-label">Producto <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                    <option value="">Selecciona un producto</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>{{ $product->name }}@if ($product->code) — {{ $product->code }}@elseif ($product->barcode) — {{ $product->barcode }}@endif</option>
                    @endforeach
                </select>
                @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif

        @unless ($isCabinet)
            <div class="mb-3">
                <label for="location_id" class="form-label">Ubicación <span class="text-body-secondary fw-normal">(Opcional)</span></label>
                <select id="location_id" name="location_id" class="form-select @error('location_id') is-invalid @enderror">
                    <option value="">Sin ubicación</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('location_id', $inventoryItem->location_id ?? '') === (string) $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endunless

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label for="minimum_stock" class="form-label">Stock mínimo <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" id="minimum_stock" name="minimum_stock" value="{{ old('minimum_stock', $inventoryItem->minimum_stock ?? '') }}" class="form-control @error('minimum_stock') is-invalid @enderror" min="0" step="0.001" required>
                @error('minimum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="maximum_stock" class="form-label">Stock máximo <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="number" id="maximum_stock" name="maximum_stock" value="{{ old('maximum_stock', $inventoryItem->maximum_stock ?? '') }}" class="form-control @error('maximum_stock') is-invalid @enderror" min="0" step="0.001" required>
                <div class="form-text">El stock máximo debe ser mayor al mínimo.</div>
                @error('maximum_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ $cancelRoute }}" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Guardar configuración</button>
    </div>
</form>
