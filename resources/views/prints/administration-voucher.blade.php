@php
    $summaryRows = $voucher->items->count();
    $detailRowsCount = $detailRows->count();
    $backUrl = route('administration-vouchers.show', $voucher);
@endphp
@extends('layouts.print')
@section('title', 'Vale de Administración #'.$voucher->id)
@section('document')
<h1>Vale de Administración</h1>
<div class="folio">Folio interno #{{ $voucher->id }} · {{ $voucher->status->label() }}</div>
<section class="meta" aria-label="Datos del vale">
    <div class="meta-item meta-item-wide"><span class="label">Almacén de origen</span><span class="value">{{ $voucher->warehouse->name }}</span></div>
    <div class="meta-item meta-item-wide"><span class="label">Gabinete de destino</span><span class="value">{{ $voucher->cabinet->name }}</span></div>
    <div class="meta-item meta-item-wide"><span class="label">Solicitó</span><span class="value">{{ $voucher->requester->name }} {{ $voucher->requester->last_name_one }}</span></div>
    <div class="meta-item"><span class="label">Fecha de solicitud</span><span class="value">{{ $voucher->requested_at->format('d/m/Y H:i') }}</span></div>
    <div class="meta-item"><span class="label">Fecha de conclusión</span><span class="value">{{ $voucher->completed_at?->format('d/m/Y H:i') ?: 'Surtido parcial' }}</span></div>
</section>
<h2 class="section-title">Productos de reposición</h2>
<table aria-label="Productos del vale">
    <thead><tr><th style="width:46%">Producto</th><th class="number">Solicitado</th><th class="number">Surtido</th><th class="number">Pendiente</th><th>Unidad</th></tr></thead>
    <tbody>@foreach($voucher->items as $item)<tr><td>{{ $item->product->name }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($item->pendingQuantity()) }}</td><td>{{ $item->product->unit->abbreviation ?: $item->product->unit->name }}</td></tr>@endforeach</tbody>
</table>
<h2 class="section-title">Trazabilidad de reposiciones</h2>
<table aria-label="Lotes transferidos">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Lote origen</th><th>Lote destino</th><th>Caducidad</th><th class="number">Cantidad</th></tr></thead>
    <tbody>@foreach($detailRows as $row)<tr><td>{{ $row['transfer']->transferred_at->format('d/m/Y H:i') }}</td><td>{{ $row['item']->sourceInventoryItem->product->name }}</td><td>{{ $row['allocation']->sourceBatch->internal_lot }}</td><td>{{ $row['allocation']->destinationBatch->internal_lot }}</td><td>{{ $row['allocation']->sourceBatch->expiration_date?->format('d/m/Y') ?: 'Sin caducidad' }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($row['allocation']->quantity) }}</td></tr>@endforeach</tbody>
</table>
@if($voucher->notes)<h2 class="section-title">Notas</h2><div class="notes">{{ $voucher->notes }}</div>@endif
<div class="signatures"><div class="signature">Firma de Almacén</div><div class="signature">Firma de recepción de Gabinete</div></div>
<div class="print-note">Responsable de la última reposición: {{ $voucher->transfers->last()->transferredBy->name }} {{ $voucher->transfers->last()->transferredBy->last_name_one }}</div>
@endsection
