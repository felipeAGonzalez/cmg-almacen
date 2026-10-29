@extends('layouts.app')
@section('page-title', 'Vale de Administración #'.$voucher->id)
@section('content')
<div class="d-flex justify-content-between mb-4"><div><h2>Vale de Administración #{{ $voucher->id }}</h2><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span>@if($voucher->consumptions->isNotEmpty())<span class="badge text-bg-info ms-1">Generado por consumo de gabinete</span>@endif</div><div class="d-flex flex-wrap gap-2">@if($voucher->transfers->isNotEmpty())<a href="{{ route('administration-vouchers.print', $voucher) }}" class="btn btn-outline-primary" target="_blank"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir vale</a>@endif @can('update',$voucher)<a href="{{ route('administration-vouchers.edit', $voucher) }}" class="btn btn-outline-primary"><i class="bi bi-pencil-square me-1"></i>Modificar pedido</a>@endcan<a href="{{ route('administration-vouchers.index') }}" class="btn btn-outline-secondary">Volver</a></div></div>
<div class="card card-body border-0 shadow-sm mb-4"><div class="row g-3"><div class="col-md-3"><small>Almacén</small><strong class="d-block">{{ $voucher->warehouse->name }}</strong></div><div class="col-md-3"><small>Gabinete</small><strong class="d-block">{{ $voucher->cabinet->name }}</strong></div><div class="col-md-3"><small>Solicitó</small><strong class="d-block">{{ $voucher->requester->name }} {{ $voucher->requester->last_name_one }}</strong></div><div class="col-md-3"><small>Fecha</small><strong class="d-block">{{ $voucher->requested_at->format('d/m/Y H:i') }}</strong></div><div class="col-12"><small>Notas</small><p class="mb-0">{{ $voucher->notes ?: 'Sin notas' }}</p></div>@if($voucher->rejection_reason)<div class="col-12"><small>Motivo del rechazo</small><p>{{ $voucher->rejection_reason }}</p></div>@endif</div></div>
<div class="card border-0 shadow-sm mb-4"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Producto</th><th>Solicitado</th><th>Surtido</th><th>Pendiente</th><th>Unidad</th></tr></thead><tbody>@foreach($voucher->items as $item)<tr><td>{{ $item->product->name }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }}</td><td class="fw-bold">{{ \App\Models\InventoryItem::formatQuantity($item->pendingQuantity()) }}</td><td>{{ $item->product->unit->name }}</td></tr>@endforeach</tbody></table></div></div>
@can('fulfill',$voucher) @if(in_array($voucher->status,[\App\Enums\AdministrationVoucherStatus::PENDING,\App\Enums\AdministrationVoucherStatus::PARTIALLY_SUPPLIED],true))
<section class="card card-body border-0 shadow-sm mb-4">
    <h3 class="h5">Surtir vale</h3>
    <p class="small text-body-secondary">Indica únicamente lo que entregarás en esta reposición. Puedes realizar un surtido parcial.</p>
    <form method="POST" action="{{ route('administration-vouchers.fulfillments.store',$voucher) }}" data-disable-submit-form>
        @csrf
        @foreach($voucher->items as $i=>$item)
            @if(bccomp($item->pendingQuantity(),'0',3)>0)
                @php($available = $inventoryItems->get($item->product_id)?->usableStock() ?? '0.000')
                @php($canSupplyFull = bccomp($available, $item->pendingQuantity(), 3) >= 0)
                <div class="row g-2 align-items-end border-bottom py-3" data-fulfillment-item>
                    <div class="col-md-5">
                        <strong>{{ $item->product->name }}</strong>
                        <div class="small text-body-secondary">Solicitado: {{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }} · Surtido: {{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }} · Pendiente: {{ \App\Models\InventoryItem::formatQuantity($item->pendingQuantity()) }}</div>
                        <div class="small {{ $canSupplyFull ? 'text-success' : 'text-warning fw-semibold' }}">Disponible: {{ \App\Models\InventoryItem::formatQuantity($available) }}</div>
                    </div>
                    <input type="hidden" name="items[{{ $i }}][voucher_item_id]" value="{{ $item->id }}">
                    <div class="col-md-7">
                        <label for="administration_fulfillment_quantity_{{ $item->id }}" class="form-label">Cantidad a surtir</label>
                        <div class="input-group">
                            <input
                                id="administration_fulfillment_quantity_{{ $item->id }}"
                                type="number"
                                step="0.001"
                                min="0"
                                max="{{ $item->pendingQuantity() }}"
                                value="{{ old("items.$i.quantity") }}"
                                name="items[{{ $i }}][quantity]"
                                class="form-control @error("items.$i.quantity") is-invalid @enderror"
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
                        @error("items.$i.quantity")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            @endif
        @endforeach
        <div class="mt-3"><label class="form-label">Notas (Opcional)</label><textarea name="notes" class="form-control">{{ old('notes') }}</textarea></div>
        <button class="btn btn-primary mt-3" data-submit-button>Registrar reposición</button>
    </form>
</section>
@endif @endcan
@if($voucher->status===\App\Enums\AdministrationVoucherStatus::PENDING)<div class="d-flex gap-2 mb-4">@can('reject',$voucher)<form method="POST" action="{{ route('administration-vouchers.reject',$voucher) }}" class="d-flex gap-2">@csrf<input name="rejection_reason" class="form-control" required placeholder="Motivo del rechazo"><button class="btn btn-outline-danger">Rechazar vale</button></form>@endcan @can('cancel',$voucher)<form method="POST" action="{{ route('administration-vouchers.cancel',$voucher) }}">@csrf<button class="btn btn-outline-secondary">Cancelar vale</button></form>@endcan</div>@endif
<section><h3 class="h5">Historial de reposiciones</h3>@forelse($voucher->transfers as $transfer)<div class="card card-body border-0 shadow-sm mb-3"><div class="d-flex justify-content-between"><div><strong>Transferencia #{{ $transfer->id }}</strong><div>{{ $transfer->transferred_at->format('d/m/Y H:i') }} · {{ $transfer->transferredBy->name }}</div></div><a href="{{ route('warehouses.transfers.show',[$voucher->warehouse,$transfer]) }}" class="btn btn-sm btn-outline-primary">Ver transferencia</a></div><ul class="mt-3 mb-0">@foreach($transfer->items as $item)<li>{{ $item->sourceInventoryItem->product->name }}: {{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }} @foreach($item->allocations as $allocation)<small class="d-block">Lote {{ $allocation->sourceBatch->internal_lot }} → {{ $allocation->destinationBatch->internal_lot }}: {{ \App\Models\InventoryItem::formatQuantity($allocation->quantity) }}</small>@endforeach</li>@endforeach</ul></div>@empty<p class="text-body-secondary">Aún no hay reposiciones registradas.</p>@endforelse</section>
@endsection
