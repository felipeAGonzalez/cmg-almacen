@extends('layouts.app')
@php
    $isEditing = isset($voucher);
    $selectedWarehouse = old('warehouse_id', $voucher->warehouse_id ?? '');
    $selectedCabinet = old('cabinet_id', $voucher->cabinet_id ?? '');
    $initialItems = old('items', $isEditing
        ? $voucher->items->map(fn($item) => ['product_id' => $item->product_id, 'quantity' => $item->requested_quantity])->values()->all()
        : [['product_id' => '', 'quantity' => '']]);
@endphp
@section('page-title', $isEditing ? 'Modificar vale de Administración' : 'Crear vale de Administración')
@section('content')
<h2>{{ $isEditing ? 'Modificar vale de Administración #'.$voucher->id : 'Crear vale de Administración' }}</h2><p class="text-body-secondary">{{ $isEditing ? 'Corrige el pedido antes de que comience su surtido.' : 'Solicita productos para reponer un gabinete.' }}</p>
<form method="POST" action="{{ $isEditing ? route('administration-vouchers.update', $voucher) : route('administration-vouchers.store') }}" class="card card-body border-0 shadow-sm" id="administration-voucher-form">@csrf @if($isEditing) @method('PUT') @endif
<div class="row g-3"><div class="col-md-6"><label class="form-label">Almacén</label><select name="warehouse_id" id="admin-warehouse" class="form-select @error('warehouse_id') is-invalid @enderror" required><option value="">Selecciona un almacén</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)$selectedWarehouse===(string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>@error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-6"><label class="form-label">Gabinete</label><select name="cabinet_id" id="admin-cabinet" class="form-select @error('cabinet_id') is-invalid @enderror" required><option value="">Selecciona un gabinete</option></select>@error('cabinet_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
<hr><div class="d-flex justify-content-between"><h3 class="h5">Productos</h3><button type="button" class="btn btn-outline-primary btn-sm" id="add-admin-item">Agregar producto</button></div><div id="admin-items" class="d-grid gap-3 mt-3"></div>@error('items')<div class="text-danger small">{{ $message }}</div>@enderror
<div class="mt-3"><label class="form-label">Notas <span class="text-body-secondary">(Opcional)</span></label><textarea name="notes" class="form-control">{{ old('notes', $voucher->notes ?? '') }}</textarea></div><div class="mt-4 d-flex gap-2"><a href="{{ $isEditing ? route('administration-vouchers.show', $voucher) : route('administration-vouchers.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary">{{ $isEditing ? 'Guardar cambios' : 'Crear vale' }}</button></div></form>
@php($catalog = $warehouses->mapWithKeys(fn($w)=>[$w->id=>['cabinets'=>$w->cabinets->map(fn($c)=>['id'=>$c->id,'name'=>$c->name,'products'=>$c->inventoryItems->filter(fn($ci)=>$w->inventoryItems->contains('product_id',$ci->product_id))->map(fn($ci)=>['id'=>$ci->product->id,'name'=>$ci->product->name,'commercialName'=>$ci->product->commercial_name,'code'=>$ci->product->code,'barcode'=>$ci->product->barcode,'unit'=>$ci->product->unit->name])->values()])->values()]]))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const data = @json($catalog);
    const warehouse = document.querySelector('#admin-warehouse');
    const cabinet = document.querySelector('#admin-cabinet');
    const box = document.querySelector('#admin-items');
    const oldCabinet = @json($selectedCabinet);
    const oldItems = @json($initialItems);
    let rows = oldItems;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));

    function syncRows() {
        rows = Array.from(box.children).map(row => ({
            product_id: row.querySelector('[data-admin-product]').value,
            quantity: row.querySelector('[data-admin-quantity]').value,
        }));
    }

    function products() {
        return Object.values(data[warehouse.value]?.cabinets || {}).find(item => String(item.id) === String(cabinet.value))?.products || [];
    }

    function render() {
        box.innerHTML = '';
        rows.forEach((row, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'row g-2 align-items-end border rounded p-2';
            const options = products().map(product => {
                const search = [product.name, product.commercialName, product.code, product.barcode].filter(Boolean).join(' ');
                const label = [product.name, product.commercialName ? `Comercial: ${product.commercialName}` : null, product.unit].filter(Boolean).join(' — ');
                return `<option value="${escapeHtml(product.id)}" data-product-search="${escapeHtml(search)}" ${String(product.id) === String(row.product_id) ? 'selected' : ''}>${escapeHtml(label)}</option>`;
            }).join('');
            wrapper.innerHTML = `<div class="col-md-7" data-product-picker><label class="form-label">Producto</label><input type="search" class="form-control form-control-sm mb-2" placeholder="Buscar por ingrediente activo o nombre comercial" autocomplete="off" data-product-search-input aria-label="Buscar producto ${index + 1}"><select class="form-select" data-admin-product data-product-search-select name="items[${index}][product_id]" required><option value="">Selecciona un producto</option>${options}</select></div><div class="col-md-3"><label class="form-label">Cantidad</label><input class="form-control" data-admin-quantity type="number" step="0.001" min="0.001" name="items[${index}][quantity]" value="${escapeHtml(row.quantity || '')}" required></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100">Quitar</button></div>`;
            wrapper.querySelector('button').onclick = () => {
                syncRows();
                if (rows.length > 1) {
                    rows.splice(index, 1);
                    render();
                }
            };
            box.append(wrapper);
        });
    }

    function loadCabinets() {
        cabinet.innerHTML = '<option value="">Selecciona un gabinete</option>';
        Object.values(data[warehouse.value]?.cabinets || {}).forEach(item => cabinet.add(new Option(item.name, item.id, false, String(item.id) === String(oldCabinet))));
        render();
    }

    warehouse.onchange = () => {
        rows = [{product_id: '', quantity: ''}];
        loadCabinets();
    };
    cabinet.onchange = () => {
        rows = [{product_id: '', quantity: ''}];
        render();
    };
    document.querySelector('#add-admin-item').onclick = () => {
        syncRows();
        rows.push({product_id: '', quantity: ''});
        render();
        box.lastElementChild.querySelector('[data-product-search-input]').focus();
    };
    loadCabinets();
});
</script>
@endsection
