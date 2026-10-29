@extends('layouts.app')

@section('page-title', 'Salidas')

@section('content')
    <div class="page-heading"><div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
        @if(Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif
        <li class="breadcrumb-item">{{ $warehouse->name }}</li><li class="breadcrumb-item active">Salidas</li>
    </ol></nav><h2>Salidas</h2><p>Consulta las salidas manuales de mercancía y los vales destinados a pacientes.</p><span class="badge text-bg-light border"><i class="bi bi-building me-1"></i>Almacén: {{ $warehouse->name }}</span></div><div class="d-flex flex-wrap gap-2"><a href="{{ route('warehouses.outbounds.create', [$warehouse, 'reason' => \App\Enums\InventoryOutboundReason::PATIENT_CARE->value]) }}" class="btn btn-primary"><i class="bi bi-person-vcard me-2"></i>Registrar vale manual</a><a href="{{ route('warehouses.outbounds.create', $warehouse) }}" class="btn btn-light border"><i class="bi bi-box-arrow-up-right me-2"></i>Otra salida</a></div></div>
    <form method="GET" action="{{ route('warehouses.outbounds.index', $warehouse) }}" class="admin-card p-3 mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label for="outbound-search" class="form-label">Buscar</label>
                <input id="outbound-search" type="search" name="search" value="{{ request('search') }}" maxlength="150" class="form-control" placeholder="Paciente, habitación, producto o comentario">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="outbound-reason" class="form-label">Motivo</label>
                <select id="outbound-reason" name="reason" class="form-select"><option value="">Todos</option>@foreach($reasons as $reason)<option value="{{ $reason->value }}" @selected(request('reason') === $reason->value)>{{ $reason->label() }}</option>@endforeach</select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="outbound-source" class="form-label">Origen</label>
                <select id="outbound-source" name="source" class="form-select"><option value="">Todos</option><option value="warehouse" @selected(request('source') === 'warehouse')>Almacén</option><option value="cabinet" @selected(request('source') === 'cabinet')>Gabinete</option></select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="outbound-cabinet" class="form-label">Gabinete</label>
                <select id="outbound-cabinet" name="cabinet_id" class="form-select"><option value="">Todos</option>@foreach($cabinets as $cabinet)<option value="{{ $cabinet->id }}" @selected((string) request('cabinet_id') === (string) $cabinet->id)>{{ $cabinet->name }}</option>@endforeach</select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="outbound-area" class="form-label">Área solicitante</label>
                <select id="outbound-area" name="requesting_area" class="form-select"><option value="">Todas</option>@foreach($requestingAreas as $area)<option value="{{ $area->value }}" @selected(request('requesting_area') === $area->value)>{{ $area->label() }}</option>@endforeach</select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="outbound-date-from" class="form-label">Fecha desde</label>
                <input id="outbound-date-from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="outbound-date-to" class="form-label">Fecha hasta</label>
                <input id="outbound-date-to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar</button>
                <a href="{{ route('warehouses.outbounds.index', $warehouse) }}" class="btn btn-light border">Limpiar filtros</a>
            </div>
        </div>
    </form>
    <section class="admin-card overflow-hidden"><div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Salidas registradas</h3><p class="small text-body-secondary mb-0">{{ $outbounds->total() }} {{ $outbounds->total() === 1 ? 'salida' : 'salidas' }}</p></div>
    @if($outbounds->isEmpty())<div class="empty-state"><span class="empty-state-icon"><i class="bi bi-box-arrow-up-right"></i></span><h3 class="h5">{{ request()->query() ? 'No hay salidas que coincidan con los filtros.' : 'No hay salidas registradas en este almacén.' }}</h3><p class="text-body-secondary mb-4">{{ request()->query() ? 'Modifica o limpia los filtros para ampliar la búsqueda.' : 'Registra una salida cuando la mercancía abandone el inventario por un motivo distinto a una transferencia o un vale.' }}</p>@if(request()->query())<a href="{{ route('warehouses.outbounds.index', $warehouse) }}" class="btn btn-light border">Limpiar filtros</a>@else<a href="{{ route('warehouses.outbounds.create', $warehouse) }}" class="btn btn-primary">Registrar salida</a>@endif</div>
    @else<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Fecha</th><th>Origen</th><th>Motivo / destino</th><th>Productos</th><th>Realizó</th><th>Comentario</th><th class="text-end">Acciones</th></tr></thead><tbody>@foreach($outbounds as $outbound)<tr><td class="text-nowrap">{{ $outbound->processed_at->format('d/m/Y H:i') }}</td><td><span class="badge text-bg-light border">{{ $outbound->sourceCabinet ? 'Gabinete · '.$outbound->sourceCabinet->name : 'Almacén' }}</span></td><td><span class="badge text-bg-light border">{{ $outbound->reason->label() }}</span>@if($outbound->reason === \App\Enums\InventoryOutboundReason::PATIENT_CARE)<strong class="d-block mt-2">{{ $outbound->patient_name }}</strong><span class="small text-body-secondary">{{ $outbound->room_number ? 'Habitación '.$outbound->room_number : 'Sin habitación indicada' }}</span>@endif</td><td>{{ $outbound->items_count }} {{ $outbound->items_count === 1 ? 'producto' : 'productos' }}</td><td>{{ $outbound->processedBy->name }} {{ $outbound->processedBy->last_name_one }}</td><td><span class="d-inline-block text-wrap outbound-note">{{ $outbound->notes ?: '—' }}</span></td><td class="text-end"><a href="{{ route('warehouses.outbounds.show', [$warehouse, $outbound]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>{{ $outbound->reason === \App\Enums\InventoryOutboundReason::PATIENT_CARE ? 'Ver vale #'.$outbound->id : 'Ver detalle' }}</a></td></tr>@endforeach</tbody></table></div>@if($outbounds->hasPages())<div class="border-top px-3 py-3">{{ $outbounds->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif @endif</section>
@endsection
