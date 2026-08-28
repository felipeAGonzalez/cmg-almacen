@php
    $selectedInventoryItem = (string) ($item['inventory_item_id'] ?? '');
    $inventoryItemError = $errors->first("items.$index.inventory_item_id");
    $quantityError = $errors->first("items.$index.quantity");
    $unitCostError = $errors->first("items.$index.unit_cost");
    $manufacturerLotError = $errors->first("items.$index.manufacturer_lot");
    $expirationError = $errors->first("items.$index.expiration_date");
@endphp
<fieldset class="entry-item" data-entry-item>
    <legend class="entry-item-title">Partida <span data-entry-item-number>{{ is_numeric($index) ? ((int) $index + 1) : '' }}</span></legend>
    <div class="row g-3 align-items-start">
        <div class="col-12 col-xl-4">
            <label class="form-label">Producto <span class="text-danger">*</span></label>
            <select name="items[{{ $index }}][inventory_item_id]" class="form-select {{ $inventoryItemError ? 'is-invalid' : '' }}" data-entry-product required>
                <option value="">Selecciona un producto</option>
                @foreach ($inventoryItems as $inventoryItem)
                    @php($identifier = $inventoryItem->product->code ?: $inventoryItem->product->barcode)
                    <option value="{{ $inventoryItem->id }}" @selected($selectedInventoryItem === (string) $inventoryItem->id)>
                        {{ $inventoryItem->product->name }}@if($identifier) — {{ $identifier }}@endif — {{ $inventoryItem->product->unit->name }}
                    </option>
                @endforeach
            </select>
            @if($inventoryItemError)<div class="invalid-feedback">{{ $inventoryItemError }}</div>@endif
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label">Cantidad <span class="text-danger">*</span></label>
            <input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" value="{{ $item['quantity'] ?? '' }}" class="form-control {{ $quantityError ? 'is-invalid' : '' }}" data-entry-quantity required>
            @if($quantityError)<div class="invalid-feedback">{{ $quantityError }}</div>@endif
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label">Costo unitario <span class="text-danger">*</span></label>
            <div class="input-group has-validation">
                <span class="input-group-text">$</span>
                <input name="items[{{ $index }}][unit_cost]" type="number" step="0.0001" min="0" value="{{ $item['unit_cost'] ?? '' }}" class="form-control {{ $unitCostError ? 'is-invalid' : '' }}" data-entry-unit-cost required>
                @if($unitCostError)<div class="invalid-feedback">{{ $unitCostError }}</div>@endif
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <label class="form-label">Lote del fabricante <span class="text-body-secondary fw-normal">(Opcional)</span></label>
            <input name="items[{{ $index }}][manufacturer_lot]" value="{{ $item['manufacturer_lot'] ?? '' }}" class="form-control {{ $manufacturerLotError ? 'is-invalid' : '' }}" placeholder="Ej. LOT-ABC123" maxlength="255">
            <div class="form-text">Déjalo vacío si el fabricante no indica un lote.</div>
            @if($manufacturerLotError)<div class="invalid-feedback">{{ $manufacturerLotError }}</div>@endif
        </div>
        <div class="col-12 col-md-6 col-xl-2">
            <label class="form-label" data-expiration-label>Caducidad <span class="text-body-secondary fw-normal" data-expiration-state>(Opcional)</span></label>
            <input name="items[{{ $index }}][expiration_date]" type="date" value="{{ $item['expiration_date'] ?? '' }}" class="form-control {{ $expirationError ? 'is-invalid' : '' }}" data-entry-expiration>
            @if($expirationError)<div class="invalid-feedback">{{ $expirationError }}</div>@endif
        </div>
    </div>
    <div class="entry-item-footer">
        <span>Subtotal: <strong data-entry-subtotal>$0.00</strong></span>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-entry-item><i class="bi bi-x-circle me-1"></i>Quitar</button>
    </div>
</fieldset>
