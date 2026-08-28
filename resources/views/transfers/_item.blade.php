@php
    $selectedId = (string) ($item['source_inventory_item_id'] ?? '');
    $productError = $errors->first("items.$index.source_inventory_item_id");
    $quantityError = $errors->first("items.$index.quantity");
@endphp
<fieldset class="transfer-item" data-transfer-item>
    <legend class="transfer-item-title">Producto <span data-transfer-item-number>{{ is_numeric($index) ? ((int) $index + 1) : '' }}</span></legend>
    <div class="row g-3 align-items-start">
        <div class="col-12 col-lg-6"><label class="form-label">Producto <span class="text-danger">*</span></label><select name="items[{{ $index }}][source_inventory_item_id]" class="form-select {{ $productError ? 'is-invalid' : '' }}" data-transfer-product required><option value="">Selecciona un producto</option>@foreach($inventoryItems as $inventoryItem)@php($identifier = $inventoryItem->product->code ?: $inventoryItem->product->barcode)<option value="{{ $inventoryItem->id }}" @selected($selectedId === (string) $inventoryItem->id)>{{ $inventoryItem->product->name }}@if($identifier) — {{ $identifier }}@endif — {{ $inventoryItem->product->unit->name }}</option>@endforeach</select>@if($productError)<div class="invalid-feedback">{{ $productError }}</div>@endif<div class="form-text text-danger d-none" data-transfer-incompatible>Este producto no está configurado en el gabinete seleccionado.</div></div>
        <div class="col-6 col-lg-2"><label class="form-label">Existencia utilizable</label><div class="transfer-stock" data-transfer-stock>—</div></div>
        <div class="col-6 col-lg-3"><label class="form-label">Cantidad <span class="text-danger">*</span></label><div class="input-group has-validation"><input name="items[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" value="{{ $item['quantity'] ?? '' }}" class="form-control {{ $quantityError ? 'is-invalid' : '' }}" data-transfer-quantity required><span class="input-group-text" data-transfer-unit>Unidad</span>@if($quantityError)<div class="invalid-feedback">{{ $quantityError }}</div>@endif</div><div class="form-text text-danger d-none" data-transfer-stock-warning>La cantidad supera la existencia utilizable.</div></div>
        <div class="col-12 col-lg-1 d-flex align-items-lg-end"><button type="button" class="btn btn-outline-danger w-100" data-remove-transfer-item><i class="bi bi-x-circle me-1"></i>Quitar</button></div>
    </div>
</fieldset>
