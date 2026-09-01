@extends('layouts.app')
@php
$isCabinet = $cabinet instanceof \App\Models\Cabinet;
$params = $isCabinet ? [$warehouse, $cabinet, $inventoryItem] : [$warehouse, $inventoryItem];
$prefix = $isCabinet ? 'warehouses.cabinets.inventory.adjustments' : 'warehouses.inventory.adjustments';
$inventoryRoute = route($isCabinet ? 'warehouses.cabinets.inventory.index' : 'warehouses.inventory.index', $isCabinet ? [$warehouse, $cabinet] : [$warehouse]);
$kardexRoute = route($isCabinet ? 'warehouses.cabinets.kardex.index' : 'warehouses.kardex.index', array_merge($isCabinet ? [$warehouse, $cabinet] : [$warehouse], ['inventory_item_id' => $inventoryItem->id]));
@endphp
@section('page-title', 'Ajustes de inventario')
@section('content')
<div class="page-heading"><div>
<nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ Auth::user()->isAdmin() ? route('warehouses.index') : route('home') }}">{{ Auth::user()->isAdmin() ? 'Almacenes' : 'Inicio' }}</a></li><li class="breadcrumb-item">{{ $warehouse->name }}</li>@if($isCabinet)<li class="breadcrumb-item">{{ $cabinet->name }}</li>@endif<li class="breadcrumb-item"><a href="{{ $inventoryRoute }}">Inventario</a></li><li class="breadcrumb-item">{{ $inventoryItem->product->name }}</li><li class="breadcrumb-item active">Ajustes</li></ol></nav>
<h2>Ajustes de inventario</h2><p>Consulta las correcciones realizadas después de un conteo físico.</p>
<div class="d-flex flex-wrap gap-2"><span class="badge text-bg-light border">Producto: {{ $inventoryItem->product->name }}</span><span class="badge text-bg-light border">Unidad: {{ $inventoryItem->product->unit->name }}</span><span class="badge text-bg-light border">Almacén: {{ $warehouse->name }}</span>@if($isCabinet)<span class="badge text-bg-light border">Gabinete: {{ $cabinet->name }}</span>@endif</div>
</div><div class="d-flex flex-wrap gap-2"><a href="{{ $kardexRoute }}" class="btn btn-light border">Ver Kardex</a><a href="{{ route($prefix.'.create', $params) }}" class="btn btn-primary">Registrar ajuste</a></div></div>
<section class="admin-card overflow-hidden"><div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Historial de ajustes</h3><p class="small text-body-secondary mb-0">{{ $adjustments->total() }} {{ $adjustments->total() === 1 ? 'ajuste' : 'ajustes' }}</p></div>
@if($adjustments->isEmpty())<div class="empty-state"><span class="empty-state-icon"><i class="bi bi-clipboard-check"></i></span><h3 class="h5">No hay ajustes registrados para este producto.</h3><p class="text-body-secondary">Los ajustes se utilizan cuando el conteo físico no coincide con la existencia registrada.</p><a href="{{ route($prefix.'.create', $params) }}" class="btn btn-primary">Registrar ajuste</a></div>
@else<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Fecha</th><th>Lote</th><th>Existencia anterior</th><th>Cantidad contada</th><th>Diferencia</th><th>Motivo</th><th>Realizó</th><th class="text-end">Acción</th></tr></thead><tbody>
@foreach($adjustments as $adjustment) @php($positive = bccomp($adjustment->difference, '0', 3) > 0)
<tr><td class="text-nowrap">{{ $adjustment->adjusted_at->format('d/m/Y H:i') }}</td><td><strong class="d-block">{{ $adjustment->inventoryBatch->internal_lot }}</strong><span class="small text-body-secondary d-block">Fabricante: {{ $adjustment->inventoryBatch->manufacturer_lot ?: 'No indicado' }}</span><span class="small text-body-secondary">{{ $adjustment->inventoryBatch->expiration_date ? 'Caduca: '.$adjustment->inventoryBatch->expiration_date->format('d/m/Y') : 'Sin caducidad' }}</span></td><td>{{ \App\Models\InventoryItem::formatQuantity($adjustment->previous_quantity) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($adjustment->counted_quantity) }}</td><td><span class="badge {{ $positive ? 'text-bg-success' : 'text-bg-danger' }}">{{ $positive ? '+' : '' }}{{ \App\Models\InventoryItem::formatQuantity($adjustment->difference) }}</span></td><td>{{ $adjustment->reason->label() }}</td><td>{{ $adjustment->adjustedBy->name }}</td><td class="text-end"><a href="{{ route($prefix.'.show', array_merge($params, [$adjustment])) }}" class="btn btn-sm btn-outline-primary text-nowrap">Ver detalle</a></td></tr>
@endforeach</tbody></table></div>@if($adjustments->hasPages())<div class="border-top px-3 py-3">{{ $adjustments->links('pagination::bootstrap-5') }}</div>@endif @endif
</section>
@endsection
