@extends('layouts.app')

@section('page-title', 'Vale de Enfermería #'.$voucher->id)

@section('content')
@php
    $isOpen = in_array($voucher->status, [\App\Enums\NursingVoucherStatus::PENDING, \App\Enums\NursingVoucherStatus::PARTIALLY_SUPPLIED], true);
    $canFulfill = Auth::user()->can('fulfill', $voucher) && $isOpen;
    $canReject = Auth::user()->can('reject', $voucher) && $voucher->status === \App\Enums\NursingVoucherStatus::PENDING;
    $canCancel = Auth::user()->can('cancel', $voucher) && $voucher->status === \App\Enums\NursingVoucherStatus::PENDING;
@endphp
<div class="page-heading"><div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li><li class="breadcrumb-item"><a href="{{ route('nursing-vouchers.index') }}">Vales de Enfermería</a></li><li class="breadcrumb-item active">Vale #{{ $voucher->id }}</li></ol></nav><div class="d-flex flex-wrap align-items-center gap-2"><h2 class="mb-0">Vale de Enfermería #{{ $voucher->id }}</h2><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span></div></div><a href="{{ route('nursing-vouchers.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-2"></i>Volver a vales</a></div>

@if($errors->any())<div class="alert alert-danger" role="alert"><strong>No fue posible completar la operación.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<section class="admin-card mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Datos del vale</h3></div><div class="p-4"><div class="row g-4">
    <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Fecha de solicitud</span><strong class="d-block">{{ $voucher->requested_at->format('d/m/Y H:i') }}</strong></div>
    <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Paciente</span><strong class="d-block">{{ $voucher->patient_name }}</strong></div>
    <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Habitación</span><strong class="d-block">{{ $voucher->room_number }}</strong></div>
    <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Solicitó</span><strong class="d-block">{{ $voucher->requester->name }} {{ $voucher->requester->last_name_one }}</strong></div>
    <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Almacén</span><strong class="d-block">{{ $voucher->warehouse->name }}</strong></div>
    <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Origen</span><strong class="d-block">{{ $voucher->source_type === \App\Enums\NursingSupplySourceType::WAREHOUSE ? 'Almacén' : 'Gabinete: '.$voucher->sourceCabinet->name }}</strong><small class="text-body-secondary">Atención: {{ $voucher->source_type === \App\Enums\NursingSupplySourceType::WAREHOUSE ? 'Almacén' : 'Gabinete' }}</small></div>
    @if($voucher->completed_at)<div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Fecha de conclusión</span><strong class="d-block">{{ $voucher->completed_at->format('d/m/Y H:i') }}</strong></div>@endif
    <div class="col-12"><span class="entry-detail-label">Notas</span><p class="mb-0">{{ $voucher->notes ?: 'Sin notas' }}</p></div>
    @if($voucher->status === \App\Enums\NursingVoucherStatus::REJECTED)<div class="col-12"><span class="entry-detail-label">Motivo del rechazo</span><p class="mb-0 text-danger">{{ $voucher->rejection_reason }}</p></div>@endif
</div></div></section>

<section class="admin-card overflow-hidden mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Productos solicitados</h3></div><div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>Producto</th><th>Solicitado</th><th>Surtido</th><th>Pendiente</th><th>Unidad</th></tr></thead><tbody>@foreach($voucher->items as $item)<tr><td class="fw-semibold">{{ $item->product->name }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }}</td><td>@if(bccomp($item->pendingQuantity(), '0', 3) > 0)<span class="badge text-bg-warning">{{ \App\Models\InventoryItem::formatQuantity($item->pendingQuantity()) }}</span>@else<span class="text-success">0</span>@endif</td><td>{{ $item->product->unit->abbreviation ?: $item->product->unit->name }}</td></tr>@endforeach</tbody></table></div></section>

@if($canFulfill)
<section class="admin-card mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Surtir vale</h3><p class="small text-body-secondary mb-0">Indica únicamente lo que entregarás en este surtido. Puedes realizar una entrega parcial.</p></div>
<form method="POST" action="{{ route('nursing-vouchers.fulfillments.store', $voucher) }}" class="p-3" data-disable-submit-form>@csrf
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Producto</th><th>Solicitado</th><th>Ya surtido</th><th>Pendiente</th><th>Disponible</th><th style="min-width:180px">Cantidad a surtir</th></tr></thead><tbody>
    @foreach($voucher->items->filter(fn($item) => bccomp($item->pendingQuantity(), '0', 3) > 0)->values() as $index => $item)
        @php($available = $inventoryItems->get($item->product_id)?->usableStock() ?? '0.000')
        <tr><td class="fw-semibold">{{ $item->product->name }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->pendingQuantity()) }}</td><td><span class="{{ bccomp($available, $item->pendingQuantity(), 3) < 0 ? 'text-warning fw-semibold' : 'text-success' }}">Disponible: {{ \App\Models\InventoryItem::formatQuantity($available) }}</span></td><td><input type="hidden" name="items[{{ $index }}][voucher_item_id]" value="{{ $item->id }}"><input type="number" name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity") }}" min="0" max="{{ $item->pendingQuantity() }}" step="0.001" class="form-control @error("items.$index.quantity") is-invalid @enderror" placeholder="0"><small class="text-body-secondary">{{ $item->product->unit->abbreviation ?: $item->product->unit->name }}</small>@error("items.$index.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror</td></tr>
    @endforeach
    </tbody></table></div>
    <div class="mb-3"><label for="fulfillment_notes" class="form-label">Notas del surtido <span class="text-body-secondary">(Opcional)</span></label><textarea id="fulfillment_notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
    <button class="btn btn-primary" type="submit" data-submit-button><i class="bi bi-box-arrow-right me-2"></i>Registrar surtido</button>
</form></section>
@endif

<section class="admin-card mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Historial de surtidos</h3></div>
@if($voucher->fulfillments->isEmpty())<div class="p-4 text-body-secondary">Este vale todavía no tiene surtidos registrados.</div>@else<div class="accordion accordion-flush" id="fulfillmentHistory">@foreach($voucher->fulfillments->sortByDesc('supplied_at') as $fulfillment)<div class="accordion-item"><h4 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#fulfillment-{{ $fulfillment->id }}">{{ $fulfillment->supplied_at->format('d/m/Y H:i') }} · {{ $fulfillment->suppliedBy->name }} {{ $fulfillment->suppliedBy->last_name_one }}</button></h4><div id="fulfillment-{{ $fulfillment->id }}" class="accordion-collapse collapse" data-bs-parent="#fulfillmentHistory"><div class="accordion-body"><p class="small text-body-secondary">{{ $fulfillment->notes ?: 'Sin notas' }}</p>@foreach($fulfillment->items as $fulfillmentItem)<div class="mb-3"><strong>{{ $fulfillmentItem->voucherItem->product->name }} — {{ \App\Models\InventoryItem::formatQuantity($fulfillmentItem->quantity) }}</strong><div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>Lote interno</th><th>Lote fabricante</th><th>Caducidad</th><th>Cantidad tomada</th></tr></thead><tbody>@foreach($fulfillmentItem->allocations as $allocation)<tr><td><code>{{ $allocation->inventoryBatch->internal_lot }}</code></td><td>{{ $allocation->inventoryBatch->manufacturer_lot ?: 'No indicado' }}</td><td>{{ $allocation->inventoryBatch->expiration_date?->format('d/m/Y') ?: 'Sin caducidad' }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($allocation->quantity) }}</td></tr>@endforeach</tbody></table></div></div>@endforeach</div></div></div>@endforeach</div>@endif
</section>

@if($canReject || $canCancel)<div class="d-flex flex-wrap gap-2 justify-content-end">
@if($canReject)<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectVoucherModal">Rechazar vale</button>@endif
@if($canCancel)<form method="POST" action="{{ route('nursing-vouchers.cancel', $voucher) }}" onsubmit="return confirm('¿Confirmas que deseas cancelar este vale?');">@csrf<button class="btn btn-outline-secondary" type="submit">Cancelar vale</button></form>@endif
</div>@endif

@if($canReject)<div class="modal fade" id="rejectVoucherModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="POST" action="{{ route('nursing-vouchers.reject', $voucher) }}">@csrf<div class="modal-header"><h2 class="modal-title fs-5">Rechazar vale</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><label for="rejection_reason" class="form-label">Motivo del rechazo</label><textarea id="rejection_reason" name="rejection_reason" rows="3" maxlength="2000" required class="form-control">{{ old('rejection_reason') }}</textarea></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cerrar</button><button type="submit" class="btn btn-danger">Confirmar rechazo</button></div></form></div></div></div>@endif
@endsection
