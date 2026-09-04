<div class="border rounded-3 p-3 bg-body-tertiary" data-voucher-item>
    <div class="row g-3 align-items-start">
        <div class="col-lg-7">
            <label class="form-label fw-semibold" for="voucher-product-{{ $index }}">Producto <span data-voucher-item-number>{{ is_numeric($index) ? $index + 1 : '' }}</span></label>
            <select id="voucher-product-{{ $index }}" name="items[{{ $index }}][product_id]" class="form-select @error("items.$index.product_id") is-invalid @enderror" required data-voucher-product>
                <option value="">Selecciona un producto</option>
                @foreach($inventoryItems as $inventoryItem)
                    @php($product = $inventoryItem->product)
                    <option value="{{ $product->id }}" @selected((string)($row['product_id'] ?? '') === (string)$product->id)>
                        {{ $product->name }} — {{ $product->unit->abbreviation ?: $product->unit->name }}@if($product->code) — {{ $product->code }}@endif @if($product->barcode) — {{ $product->barcode }}@endif
                    </option>
                @endforeach
            </select>
            @error("items.$index.product_id")<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text" data-voucher-product-detail>Selecciona por nombre, código o código de barras.</div>
        </div>
        <div class="col-sm-8 col-lg-3">
            <label class="form-label fw-semibold" for="voucher-quantity-{{ $index }}">Cantidad solicitada</label>
            <div class="input-group">
                <input id="voucher-quantity-{{ $index }}" type="number" min="0.001" step="0.001" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? '' }}" class="form-control @error("items.$index.quantity") is-invalid @enderror" required data-voucher-quantity>
                <span class="input-group-text" data-voucher-unit>Unidad</span>
                @error("items.$index.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-sm-4 col-lg-2 d-grid">
            <label class="form-label d-none d-sm-block">&nbsp;</label>
            <button type="button" class="btn btn-outline-danger" data-remove-voucher-item><i class="bi bi-trash me-1"></i>Eliminar</button>
        </div>
    </div>
</div>
