@php
    $summaryRows = $outbound->items->count();
    $detailRowsCount = $detailRows->count();
    $backUrl = route('warehouses.outbounds.show', [$warehouse, $outbound]);
@endphp
@extends('layouts.print')
@section('title', 'Vale manual #'.$outbound->id)
@section('document')
<h1>Solicitud de medicamentos</h1>
<div class="folio">Vale manual · Folio interno #{{ $outbound->id }}</div>
<section class="meta" aria-label="Datos del vale">
    <div class="meta-item meta-item-wide"><span class="label">Nombre del paciente</span><span class="value">{{ $outbound->patient_name }}</span></div>
    <div class="meta-item"><span class="label">Habitación</span><span class="value">{{ $outbound->room_number ?: 'No indicada' }}</span></div>
    <div class="meta-item"><span class="label">Fecha y hora</span><span class="value">{{ $outbound->processed_at->format('d/m/Y H:i') }}</span></div>
    <div class="meta-item meta-item-wide"><span class="label">Área solicitante</span><span class="value">{{ $outbound->requestingAreaLabel() }}</span></div>
    <div class="meta-item meta-item-wide"><span class="label">Origen del inventario</span><span class="value">{{ $outbound->sourceCabinet ? 'Gabinete · '.$outbound->sourceCabinet->name : 'Almacén · '.$warehouse->name }}</span></div>
</section>
<h2 class="section-title">Medicamentos y/o material de curación</h2>
<table aria-label="Productos entregados">
    <thead><tr><th style="width:54%">Producto</th><th class="number">Cantidad</th><th>Unidad</th><th>Lote</th><th>Caducidad</th></tr></thead>
    <tbody>@foreach($detailRows as $row)<tr><td>{{ $row['item']->inventoryItem->product->name }}</td><td class="number">{{ \App\Models\InventoryItem::formatQuantity($row['allocation']->quantity) }}</td><td>{{ $row['item']->inventoryItem->product->unit->abbreviation ?: $row['item']->inventoryItem->product->unit->name }}</td><td>{{ $row['allocation']->inventoryBatch->internal_lot }}</td><td>{{ $row['allocation']->inventoryBatch->expiration_date?->format('d/m/Y') ?: 'Sin caducidad' }}</td></tr>@endforeach</tbody>
</table>
@if($outbound->notes)<h2 class="section-title">Notas</h2><div class="notes">{{ $outbound->notes }}</div>@endif
<div class="signatures"><div class="signature">Firma de quien entrega</div><div class="signature">Firma de Enfermería / solicitante</div></div>
<div class="print-note">Registró: {{ $outbound->processedBy->name }} {{ $outbound->processedBy->last_name_one }}</div>
@endsection
