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

@can('requestReturn', $voucher)
    @if(collect($returnableQuantities)->contains(fn($quantity) => bccomp($quantity, '0', 3) > 0))
        <section class="admin-card mb-4" data-nursing-return-form>
            <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h3 class="h6 fw-bold mb-1">Solicitar devolución</h3>
                    <p class="small text-body-secondary mb-0">Puedes devolver uno, varios o todos los productos que ya fueron surtidos.</p>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" data-fill-all-returnable>
                    <i class="bi bi-check2-all me-1" aria-hidden="true"></i>Devolver todo el vale
                </button>
            </div>
            <form method="POST" action="{{ route('nursing-vouchers.returns.store', $voucher) }}" class="p-3" data-disable-submit-form>
                @csrf
                <div class="table-responsive">
                    <table class="table align-middle mb-3">
                        <thead><tr><th>Producto</th><th>Surtido</th><th>Disponible para devolver</th><th style="min-width:300px">Cantidad a devolver</th></tr></thead>
                        <tbody>
                        @foreach($voucher->items as $index => $item)
                            @php($returnable = $returnableQuantities[$item->id] ?? '0.000')
                            @if(bccomp($returnable, '0', 3) > 0)
                                <tr data-returnable-item>
                                    <td class="fw-semibold">{{ $item->product->name }}</td>
                                    <td>{{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }}</td>
                                    <td><span class="text-success fw-semibold">{{ \App\Models\InventoryItem::formatQuantity($returnable) }}</span></td>
                                    <td>
                                        <input type="hidden" name="items[{{ $index }}][voucher_item_id]" value="{{ $item->id }}">
                                        <div class="input-group">
                                            <input type="number" name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity") }}" min="0" max="{{ $returnable }}" step="0.001" placeholder="0" class="form-control" data-return-quantity>
                                            <button type="button" class="btn btn-outline-success" data-fill-returnable data-returnable-quantity="{{ $returnable }}">
                                                Devolver completo
                                            </button>
                                        </div>
                                        <small class="text-body-secondary">{{ $item->product->unit->abbreviation ?: $item->product->unit->name }}</small>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mb-3">
                    <label for="return_notes" class="form-label">Notas de la devolución <span class="text-body-secondary">(Opcional)</span></label>
                    <textarea id="return_notes" name="notes" rows="2" maxlength="2000" class="form-control">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="btn btn-primary" data-submit-button>
                    <i class="bi bi-arrow-return-left me-2" aria-hidden="true"></i>Solicitar devolución
                </button>
            </form>
        </section>
    @endif
@endcan

@if($voucher->returns->isNotEmpty())
    <section class="admin-card mb-4">
        <div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Devoluciones</h3></div>
        <div class="accordion accordion-flush" id="returnHistory">
            @foreach($voucher->returns->sortByDesc('requested_at') as $return)
                @php($returnRequiresAction = $return->status === \App\Enums\NursingVoucherReturnStatus::PENDING)
                <div class="accordion-item">
                    <h4 class="accordion-header">
                        <button
                            class="accordion-button {{ $returnRequiresAction ? '' : 'collapsed' }}"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#return-{{ $return->id }}"
                            aria-expanded="{{ $returnRequiresAction ? 'true' : 'false' }}"
                            aria-controls="return-{{ $return->id }}"
                        >
                            Devolución #{{ $return->id }} · {{ $return->requested_at->format('d/m/Y H:i') }}
                            <span class="badge {{ $return->status->badgeClass() }} ms-2">{{ $return->status->label() }}</span>
                            @can('receiveReturn', $voucher)
                                @if($returnRequiresAction)
                                    <span class="badge text-bg-warning ms-2">Requiere recepción</span>
                                @endif
                            @endcan
                        </button>
                    </h4>
                    <div id="return-{{ $return->id }}" class="accordion-collapse collapse {{ $returnRequiresAction ? 'show' : '' }}" data-bs-parent="#returnHistory">
                        <div class="accordion-body">
                            <p class="small text-body-secondary">Solicitó: {{ $return->requester->name }} {{ $return->requester->last_name_one }}</p>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead><tr><th>Producto</th><th>Lote</th><th>Cantidad</th></tr></thead>
                                    <tbody>
                                    @foreach($return->items as $returnItem)
                                        <tr>
                                            <td>{{ $returnItem->allocation->fulfillmentItem->voucherItem->product->name }}</td>
                                            <td><code>{{ $returnItem->allocation->inventoryBatch->internal_lot }}</code></td>
                                            <td>{{ \App\Models\InventoryItem::formatQuantity($returnItem->quantity) }} {{ $returnItem->allocation->fulfillmentItem->voucherItem->product->unit->abbreviation }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($return->notes)<p class="small mb-2"><strong>Notas:</strong> {{ $return->notes }}</p>@endif
                            @if($return->rejection_reason)<p class="small text-danger mb-2"><strong>Motivo del rechazo:</strong> {{ $return->rejection_reason }}</p>@endif
                            @if($return->receivedBy)<p class="small text-success"><strong>Recibió:</strong> {{ $return->receivedBy->name }} {{ $return->receivedBy->last_name_one }} · {{ $return->received_at->format('d/m/Y H:i') }}</p>@endif

                            @if($return->status === \App\Enums\NursingVoucherReturnStatus::PENDING)
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    @can('receiveReturn', $voucher)
                                        <form method="POST" action="{{ route('nursing-vouchers.returns.receive', [$voucher, $return]) }}" onsubmit="return confirm('¿Confirmas que recibiste físicamente estos productos?');">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm">Confirmar recepción</button>
                                        </form>
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectReturnModal{{ $return->id }}">Rechazar devolución</button>
                                    @endcan
                                    @can('cancelReturn', $voucher)
                                        <form method="POST" action="{{ route('nursing-vouchers.returns.cancel', [$voucher, $return]) }}" onsubmit="return confirm('¿Confirmas que deseas cancelar esta devolución?');">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">Cancelar solicitud</button>
                                        </form>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($return->status === \App\Enums\NursingVoucherReturnStatus::PENDING && Auth::user()->can('rejectReturn', $voucher))
                    <div class="modal fade" id="rejectReturnModal{{ $return->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog"><div class="modal-content">
                            <form method="POST" action="{{ route('nursing-vouchers.returns.reject', [$voucher, $return]) }}">
                                @csrf
                                <div class="modal-header"><h2 class="modal-title fs-5">Rechazar devolución</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                                <div class="modal-body"><label for="return_rejection_reason_{{ $return->id }}" class="form-label">Motivo del rechazo</label><textarea id="return_rejection_reason_{{ $return->id }}" name="rejection_reason" class="form-control" rows="3" required maxlength="2000"></textarea></div>
                                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cerrar</button><button type="submit" class="btn btn-danger">Confirmar rechazo</button></div>
                            </form>
                        </div></div>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endif

@if($canFulfill)
<section class="admin-card mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Surtir vale</h3><p class="small text-body-secondary mb-0">Indica únicamente lo que entregarás en este surtido. Puedes realizar una entrega parcial.</p></div>
<form method="POST" action="{{ route('nursing-vouchers.fulfillments.store', $voucher) }}" class="p-3" data-disable-submit-form>@csrf
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Producto</th><th>Solicitado</th><th>Ya surtido</th><th>Pendiente</th><th>Disponible</th><th style="min-width:280px">Cantidad a surtir</th></tr></thead><tbody>
    @foreach($voucher->items->filter(fn($item) => bccomp($item->pendingQuantity(), '0', 3) > 0)->values() as $index => $item)
        @php($available = $inventoryItems->get($item->product_id)?->usableStock() ?? '0.000')
        @php($canSupplyFull = bccomp($available, $item->pendingQuantity(), 3) >= 0)
        <tr data-fulfillment-item>
            <td class="fw-semibold">{{ $item->product->name }}</td>
            <td>{{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }}</td>
            <td>{{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }}</td>
            <td>{{ \App\Models\InventoryItem::formatQuantity($item->pendingQuantity()) }}</td>
            <td><span class="{{ $canSupplyFull ? 'text-success' : 'text-warning fw-semibold' }}">Disponible: {{ \App\Models\InventoryItem::formatQuantity($available) }}</span></td>
            <td>
                <input type="hidden" name="items[{{ $index }}][voucher_item_id]" value="{{ $item->id }}">
                <div class="input-group">
                    <input
                        id="fulfillment_quantity_{{ $item->id }}"
                        type="number"
                        name="items[{{ $index }}][quantity]"
                        value="{{ old("items.$index.quantity") }}"
                        min="0"
                        max="{{ $item->pendingQuantity() }}"
                        step="0.001"
                        class="form-control @error("items.$index.quantity") is-invalid @enderror"
                        placeholder="0"
                        data-fulfillment-quantity
                    >
                    <button
                        type="button"
                        class="btn btn-outline-success"
                        data-fill-full-quantity
                        data-full-quantity="{{ $item->pendingQuantity() }}"
                        aria-label="Surtir completo {{ $item->product->name }}"
                        title="{{ $canSupplyFull ? 'Usar toda la cantidad pendiente' : 'La existencia disponible no alcanza para surtir el pendiente completo' }}"
                        @disabled(! $canSupplyFull)
                    >
                        <i class="bi bi-check2-all me-1" aria-hidden="true"></i>Surtir completo
                    </button>
                </div>
                <small class="text-body-secondary">
                    {{ $item->product->unit->abbreviation ?: $item->product->unit->name }}
                    @unless($canSupplyFull) · Captura la cantidad que surtirás parcialmente.@endunless
                </small>
                @error("items.$index.quantity")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </td>
        </tr>
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
