@extends('layouts.app')
@section('page-title', 'Registrar devolución manual')
@section('content')
<div class="page-heading"><div><h2>Registrar devolución manual</h2><p>Reintegra al inventario productos de una salida manual previamente registrada.</p><span class="badge text-bg-light border">Almacén: {{ $warehouse->name }}</span></div></div>
<section class="admin-card mb-4">
    <div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Buscar salida original</h3><p class="small text-body-secondary mb-0">Filtra por paciente, fecha o ambos criterios.</p></div>
    <div class="p-4">
        <form method="GET" action="{{ route('warehouses.entries.returns.create', $warehouse) }}" class="row g-3 align-items-end mb-4">
            <div class="col-lg-6"><label for="patient" class="form-label">Paciente</label><input id="patient" name="patient" value="{{ $patient }}" class="form-control @error('patient') is-invalid @enderror" placeholder="Nombre o parte del nombre" maxlength="255">@error('patient')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4 col-lg-3"><label for="date" class="form-label">Fecha de salida</label><input id="date" name="date" type="date" value="{{ $date }}" class="form-control @error('date') is-invalid @enderror">@error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-8 col-lg-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Buscar</button>@if($patient !== '' || $date)<a href="{{ route('warehouses.entries.returns.create', $warehouse) }}" class="btn btn-light border" aria-label="Limpiar búsqueda"><i class="bi bi-x-lg"></i></a>@endif</div>
        </form>

        @if($outbounds->isEmpty())
            <div class="alert alert-light border mb-0"><i class="bi bi-info-circle me-2"></i>No se encontraron salidas manuales con esos criterios.</div>
        @else
            <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                <h4 class="h6 fw-bold mb-0">Salidas recientes</h4>
                <span class="small text-body-secondary">Más recientes primero · máximo 50</span>
            </div>
            <div class="table-responsive border rounded">
                <table class="table admin-table align-middle mb-0">
                    <thead><tr><th>Fecha</th><th>Origen</th><th>Paciente / destino</th><th>Habitación</th><th>Realizó</th><th class="text-end">Acción</th></tr></thead>
                    <tbody>
                        @foreach($outbounds as $outbound)
                            <tr @class(['table-primary' => $selectedOutbound?->is($outbound)])>
                                <td class="text-nowrap"><strong>{{ $outbound->processed_at->format('d/m/Y') }}</strong><span class="small text-body-secondary d-block">{{ $outbound->processed_at->format('H:i') }}</span></td>
                                <td><span class="badge text-bg-light border">{{ $outbound->sourceCabinet ? 'Gabinete · '.$outbound->sourceCabinet->name : 'Almacén' }}</span></td>
                                <td><strong>{{ $outbound->patient_name ?: $outbound->reason->label() }}</strong><span class="small text-body-secondary d-block">Salida #{{ $outbound->id }}</span></td>
                                <td>{{ $outbound->room_number ?: '—' }}</td>
                                <td>{{ $outbound->processedBy->name }} {{ $outbound->processedBy->last_name_one }}</td>
                                <td class="text-end">
                                    <a href="{{ route('warehouses.entries.returns.create', array_filter(['warehouse' => $warehouse, 'outbound' => $outbound->id, 'patient' => $patient ?: null, 'date' => $date])) }}" class="btn btn-sm {{ $selectedOutbound?->is($outbound) ? 'btn-primary' : 'btn-outline-primary' }} text-nowrap">
                                        <i class="bi bi-arrow-return-left me-1"></i>{{ $selectedOutbound?->is($outbound) ? 'Seleccionada' : 'Devolver productos' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@if($selectedOutbound)
<form method="POST" action="{{ route('warehouses.entries.returns.store', $warehouse) }}">@csrf<input type="hidden" name="inventory_outbound_id" value="{{ $selectedOutbound->id }}">
<section class="admin-card mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Productos entregados</h3></div><div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>Producto</th><th>Lote(s)</th><th>Entregado</th><th>Disponible para devolver</th><th>Cantidad</th></tr></thead><tbody>
@foreach($selectedOutbound->items as $index => $item)
@php($returned = $item->allocations->sum(fn($allocation) => (float) $allocation->returnItems->sum('quantity')))
@php($returnable = bcsub($item->requested_quantity, (string)$returned, 3))
<tr><td><strong>{{ $item->inventoryItem->product->name }}</strong><div class="small text-body-secondary">{{ $item->inventoryItem->product->unit->name }}</div></td><td>@foreach($item->allocations as $allocation)<span class="badge text-bg-light border">{{ $allocation->inventoryBatch->internal_lot }}</span> @endforeach</td><td>{{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($returnable) }}</td><td><input type="hidden" name="items[{{ $index }}][outbound_item_id]" value="{{ $item->id }}"><input class="form-control @error("items.$index.quantity") is-invalid @enderror" type="number" name="items[{{ $index }}][quantity]" value="{{ old("items.$index.quantity") }}" min="0" max="{{ $returnable }}" step="0.001" @disabled(bccomp($returnable, '0', 3) <= 0)>@error("items.$index.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror</td></tr>
@endforeach
</tbody></table></div></section>
@error('items')<div class="alert alert-danger">{{ $message }}</div>@enderror
<section class="admin-card mb-4"><div class="p-4 row g-3"><div class="col-md-4"><label for="received_at" class="form-label">Fecha de devolución</label><input id="received_at" name="received_at" type="datetime-local" max="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}" class="form-control @error('received_at') is-invalid @enderror" required>@error('received_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="col-md-8"><label for="notes" class="form-label">Notas (opcional)</label><textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div></div></section>
<div class="form-actions"><a href="{{ route('warehouses.entries.index', $warehouse) }}" class="btn btn-light border">Cancelar</a><button class="btn btn-primary"><i class="bi bi-arrow-return-left me-2"></i>Registrar devolución</button></div></form>
@endif
@endsection
