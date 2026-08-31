@php
    $selectedId = (string) ($item['inventory_item_id'] ?? '');
    $productError = $errors->first("items.$index.inventory_item_id");
    $quantityError = $errors->first("items.$index.quantity");
@endphp
<fieldset class="transfer-item" data-outbound-item>
    <legend class="transfer-item-title">Producto <span data-outbound-item-number>{{ is_numeric($index) ? ((int) $index + 1) : '' }}</span></legend>
    <div class="row g-3 align-items-start">
        <div class="col-12 col-lg-6">
            <label class="form-label">Producto <span class="text-danger">*</span></label>
            <select name="items[{{ $index }}][inventory_item_id]" class="form-select {{ $productError ? 'is-invalid' : '' }}" data-outbound-product required>
                <option value="">Selecciona un producto</option>
                @foreach($inventoryItems as $inventoryItem)
                    @php($identifier = $inventoryItem->product->code ?: $inventoryItem->product->barcode)
                    <option value="{{ $inventoryItem->id }}" @selected($selectedId === (string) $inventoryItem->id)>{{ $inventoryItem->product->name }}@if($identifier) — {{ $identifier }}@endif — {{ $inventoryItem->product->unit->name }}</option>
                @endforeach
            </select>
            @if($productError)<div class="invalid-feedback">{{ $productError }}</div>@endif
        </div>
        <div class="col-6 col-lg-2">
            <label class="form-label">Existencia utilizable</label>
            <div class="transfer-stock" data-outbound-stock>—</div>
        </div>
        <div class="col-6 col-lg-3">
            <label class="form-label">Cantidad <span class="text-danger">*</span></label>
            <div class="input-group has-validation">
                <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" value="{{ $item['quantity'] ?? '' }}" class="form-control {{ $quantityError ? 'is-invalid' : '' }}" data-outbound-quantity required>
                <span class="input-group-text" data-outbound-unit>Unidad</span>
                @if($quantityError)<div class="invalid-feedback">{{ $quantityError }}</div>@endif
            </div>
            <div class="form-text text-danger d-none" data-outbound-stock-warning>La cantidad supera la existencia utilizable.</div>
        </div>
        <div class="col-12 col-lg-1 d-flex align-items-lg-end">
            <button type="button" class="btn btn-outline-danger w-100" data-remove-outbound-item><i class="bi bi-x-circle me-1"></i>Quitar</button>
        </div>
    </div>
</fieldset>
