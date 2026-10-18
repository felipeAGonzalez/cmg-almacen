@php
    $summaryRows = $voucher->items->count();
    $detailRowsCount = $detailRows->count();
    $backUrl = route('nursing-vouchers.show', $voucher);
@endphp
@extends('layouts.print')
@section('title', 'Vale de Enfermería #'.$voucher->id)
@section('document')
<h1>Vale de Enfermería</h1>
<div class="folio">Folio interno #{{ $voucher->id }} · {{ $voucher->status->label() }}</div>
<section class="meta" aria-label="Datos del vale">
    <div class="meta-item meta-item-wide"><span class="label">Nombre del paciente</span><span class="value">{{ $voucher->patient_name }}</span></div>
    <div class="meta-item"><span class="label">Habitación</span><span class="value">{{ $voucher->room_number ?: 'No indicada' }}</span></div>
    <div class="meta-item"><span class="label">Fecha de solicitud</span><span class="value">{{ $voucher->requested_at->format('d/m/Y H:i') }}</span></div>
    <div class="meta-item meta-item-wide"><span class="label">Almacén asignado</span><span class="value">{{ $voucher->warehouse->name }}</span></div>
    <div class="meta-item"><span class="label">Origen de surtido</span><span class="value">{{ $voucher->sourceCabinet ? 'Gabinete · '.$voucher->sourceCabinet->name : 'Almacén' }}</span></div>
    <div class="meta-item"><span class="label">Solicitó</span><span class="value">{{ $voucher->requester->name }} {{ $voucher->requester->last_name_one }}</span></div>
</section>
<h2 class="section-title">Medicamentos y/o material de curación</h2>
<table aria-label="Productos del vale">
    <thead><tr><th style="width:46%">Producto</th><th class="number">Solicitado</th><th class="number">Surtido</th><th class="number">Pendiente</th><th>Unidad</th></tr></thead>
    <tbody>@foreach($voucher->items as $item)<tr><td>{{ $item->product->name }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($item->requested_quantity) }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($item->supplied_quantity) }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($item->pendingQuantity()) }}</td><td>{{ $item->product->unit->abbreviation ?: $item->product->unit->name }}</td></tr>@endforeach</tbody>
</table>
<h2 class="section-title">Trazabilidad del surtido</h2>
<table aria-label="Lotes surtidos">
    <thead><tr><th>Fecha</th><th>Producto</th><th>Lote interno</th><th>Lote fabricante</th><th>Caducidad</th><th class="number">Cantidad</th></tr></thead>
    <tbody>@foreach($detailRows as $row)<tr><td>{{ $row['fulfillment']->supplied_at->format('d/m/Y H:i') }}</td><td>{{ $row['item']->voucherItem->product->name }}</td><td>{{ $row['allocation']->inventoryBatch->internal_lot }}</td><td>{{ $row['allocation']->inventoryBatch->manufacturer_lot ?: 'No indicado' }}</td><td>{{ $row['allocation']->inventoryBatch->expiration_date?->format('d/m/Y') ?: 'Sin caducidad' }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($row['allocation']->quantity) }}</td></tr>@endforeach</tbody>
</table>
@if($voucher->notes)<h2 class="section-title">Notas</h2><div class="notes">{{ $voucher->notes }}</div>@endif
<div class="signatures"><div class="signature">Firma de quien entrega</div><div class="signature">Firma de Enfermería</div></div>
<div class="print-note">Responsable del surtido: {{ $voucher->fulfillments->last()->suppliedBy->name }} {{ $voucher->fulfillments->last()->suppliedBy->last_name_one }}</div>
@endsection
