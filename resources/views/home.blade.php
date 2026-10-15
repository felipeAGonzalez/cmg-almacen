@extends('layouts.app')

@section('page-title', 'Inicio')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h2 class="h4 mb-1">Panel de control</h2>
        <p class="text-body-secondary mb-0">Información operativa que requiere tu atención.</p>
    </div>
    <a href="{{ route('notifications.index') }}" class="btn btn-outline-primary">
        <i class="bi bi-bell me-1" aria-hidden="true"></i> Notificaciones
        @if ($unreadNotificationCount > 0)<span class="badge text-bg-danger ms-1">{{ $unreadNotificationCount }}</span>@endif
    </a>
</div>

@if (in_array($dashboardRole, [\App\Enums\UserRole::ADMINISTRATOR, \App\Enums\UserRole::ROOT], true))
    <section class="mb-4" aria-labelledby="global-summary">
        <h3 class="h5 mb-3" id="global-summary">Resumen global</h3>
        <div class="row g-3">
            @foreach ([['Almacenes', $summary['warehouses'], 'bi-building'], ['Gabinetes', $summary['cabinets'], 'bi-archive'], ['Productos configurados', $summary['configured_products'], 'bi-box-seam']] as [$label, $value, $icon])
                <div class="col-6 col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><i class="bi {{ $icon }} text-primary" aria-hidden="true"></i><div class="fs-3 fw-semibold">{{ $value }}</div><div class="text-body-secondary">{{ $label }}</div></div></div></div>
            @endforeach
        </div>
    </section>
@endif

@if ($dashboardRole === \App\Enums\UserRole::WAREHOUSE_MANAGER)
    <div class="alert alert-light border mb-4"><strong>Almacenes asignados:</strong> {{ $assignedWarehouses->pluck('name')->join(', ') ?: 'Sin almacenes asignados' }}</div>
@endif

@if ($dashboardRole === \App\Enums\UserRole::NURSE)
    <section class="mb-4" aria-labelledby="nursing-operation">
        <h3 class="h5 mb-3" id="nursing-operation">Operación de Enfermería</h3>
        @if ($nursingWarehouse)
            <div class="card border-0 shadow-sm"><div class="card-body d-flex flex-wrap justify-content-between gap-3">
                <div><span class="text-body-secondary d-block">Almacén asignado</span><strong>{{ $nursingWarehouse->name }}</strong></div>
                <div><span class="text-body-secondary d-block">Gabinete predeterminado</span><strong>{{ $nursingWarehouse->defaultNursingCabinet?->name ?? 'Sin configurar' }}</strong></div>
                <div>
                    <span class="text-body-secondary d-block">Estado operativo</span>
                    @if ($warehouseAvailable)
                        <span class="badge text-bg-success">Almacén disponible</span>
                    @else
                        <span class="badge text-bg-secondary">Almacén cerrado</span>
                        <small class="d-block mt-1">Enfermería se surte desde gabinete.</small>
                    @endif
                </div>
            </div></div>
        @else
            <div class="alert alert-warning mb-0">No tienes un almacén asignado. Solicita al administrador completar tu configuración.</div>
        @endif
    </section>
@endif

@if (isset($summary))
<section class="mb-4" aria-labelledby="attention-summary">
    <h3 class="h5 mb-3" id="attention-summary">Requiere atención</h3>
    <div class="row g-3">
        <div class="col-6 col-xl-3"><a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="{{ route('nursing-vouchers.index', ['status' => 'pending']) }}"><div class="card-body"><div class="fs-3 fw-semibold text-warning-emphasis">{{ $summary['nursing_pending'] }}</div><div>Vales de Enfermería pendientes</div></div></a></div>
        <div class="col-6 col-xl-3"><a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="{{ route('nursing-vouchers.index', ['status' => 'partially_supplied']) }}"><div class="card-body"><div class="fs-3 fw-semibold text-info-emphasis">{{ $summary['nursing_partial'] }}</div><div>Vales de Enfermería parciales</div></div></a></div>
        @if ($dashboardRole === \App\Enums\UserRole::NURSE)
            <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-semibold text-success">{{ $summary['nursing_recently_supplied'] }}</div><div>Surtidos en los últimos 7 días</div></div></div></div>
        @else
            <div class="col-6 col-xl-3"><a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="{{ route('administration-vouchers.index', ['status' => 'pending']) }}"><div class="card-body"><div class="fs-3 fw-semibold text-warning-emphasis">{{ $summary['administration_pending'] }}</div><div>Vales de Administración pendientes</div></div></a></div>
            <div class="col-6 col-xl-3"><a class="card border-0 shadow-sm h-100 text-decoration-none text-body" href="{{ route('administration-vouchers.index', ['status' => 'partially_supplied']) }}"><div class="card-body"><div class="fs-3 fw-semibold text-info-emphasis">{{ $summary['administration_partial'] }}</div><div>Vales de Administración parciales</div></div></a></div>
        @endif
    </div>
</section>
@endif

@if(isset($stockLocations))
<section class="mb-4" aria-labelledby="stock-by-location">
    <h3 class="h5 mb-3" id="stock-by-location">Stock por ubicación</h3>
    @forelse($stockLocations as $location)
        <div class="admin-card mb-3 overflow-hidden">
            <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><h4 class="h6 fw-bold mb-1"><i class="bi bi-building me-2 text-primary"></i>{{ $location['warehouse']->name }}</h4><span class="small text-body-secondary">Inventario principal del almacén</span></div>
                <a href="{{ route('inventory.index', ['inventory_scope' => $location['scope']]) }}" class="btn btn-sm btn-outline-primary">Ver inventario</a>
            </div>
            <div class="p-3 p-lg-4">
                @include('dashboard._stock-location', ['name' => 'Almacén', 'scope' => $location['scope'], 'counts' => $location['counts']])
                @if($location['cabinets']->isNotEmpty())
                    <hr class="my-4">
                    <h5 class="h6 fw-bold mb-3">Gabinetes</h5>
                    <div class="row g-3">
                        @foreach($location['cabinets'] as $cabinetLocation)
                            <div class="col-12 col-xl-6">
                                @include('dashboard._stock-location', ['name' => $cabinetLocation['cabinet']->name, 'scope' => $cabinetLocation['scope'], 'counts' => $cabinetLocation['counts']])
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="small text-body-secondary mb-0 mt-3">Este almacén no tiene gabinetes configurados.</p>
                @endif
            </div>
        </div>
    @empty
        <div class="alert alert-light border">No hay almacenes disponibles para mostrar.</div>
    @endforelse
</section>
@endif

@if (isset($configurationPending))
<section class="mb-4" aria-labelledby="pending-configuration">
    <h3 class="h5 mb-3" id="pending-configuration">Configuración pendiente</h3>
    <div class="card border-0 shadow-sm"><div class="list-group list-group-flush">
        <a href="{{ route('warehouses.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>Almacenes sin gabinete predeterminado</span><span class="badge text-bg-secondary">{{ $configurationPending['warehouses_without_cabinet'] }}</span></a>
        <a href="{{ route('users.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>Enfermeras sin almacén asignado</span><span class="badge text-bg-secondary">{{ $configurationPending['nurses_without_warehouse'] }}</span></a>
        <a href="{{ route('users.index') }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>Enfermeras sin vínculo con Hospitalización</span><span class="badge text-bg-secondary">{{ $configurationPending['nurses_without_hospital_link'] }}</span></a>
    </div></div>
</section>
@endif

@if (isset($attentionVouchers))
<section class="mb-4" aria-labelledby="manager-attention"><h3 class="h5 mb-3" id="manager-attention">Vales por atender</h3>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Tipo</th><th>Folio</th><th>Almacén</th><th>Fecha</th><th>Estado</th><th></th></tr></thead><tbody>
    @forelse ($attentionVouchers as $entry) @php($voucher = $entry['voucher'])
        <tr><td>{{ $entry['kind'] === 'nursing' ? 'Enfermería' : 'Administración' }}</td><td>#{{ $voucher->id }}</td><td>{{ $voucher->warehouse->name }}</td><td>{{ $voucher->requested_at->format('d/m/Y H:i') }}</td><td><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ $entry['kind'] === 'nursing' ? route('nursing-vouchers.show', $voucher) : route('administration-vouchers.show', $voucher) }}">Ver</a></td></tr>
    @empty <tr><td colspan="6" class="text-center text-body-secondary py-4">No hay vales pendientes de atención.</td></tr> @endforelse
    </tbody></table></div></div>
</section>
@endif

@if (isset($recentNursingVouchers))
<section class="mb-4" aria-labelledby="recent-nursing"><h3 class="h5 mb-3" id="recent-nursing">{{ $dashboardRole === \App\Enums\UserRole::NURSE ? 'Mis vales recientes' : 'Actividad reciente de Enfermería' }}</h3>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Folio</th><th>Paciente</th><th>Habitación</th><th>Almacén</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>
@forelse ($recentNursingVouchers as $voucher)<tr><td>#{{ $voucher->id }}</td><td>{{ $voucher->patient_name }}</td><td>{{ $voucher->room_number }}</td><td>{{ $voucher->warehouse->name }}</td><td><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span></td><td>{{ $voucher->requested_at->format('d/m/Y H:i') }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('nursing-vouchers.show', $voucher) }}">Ver</a></td></tr>@empty<tr><td colspan="7" class="text-center text-body-secondary py-4">No hay vales recientes.</td></tr>@endforelse
</tbody></table></div></div></section>
@endif

@if (isset($recentAdministrationVouchers))
<section class="mb-4" aria-labelledby="recent-administration"><h3 class="h5 mb-3" id="recent-administration">Actividad reciente de Administración</h3>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Folio</th><th>Almacén</th><th>Gabinete</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>
@forelse ($recentAdministrationVouchers as $voucher)<tr><td>#{{ $voucher->id }}</td><td>{{ $voucher->warehouse->name }}</td><td>{{ $voucher->cabinet->name }}</td><td><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span></td><td>{{ $voucher->requested_at->format('d/m/Y H:i') }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('administration-vouchers.show', $voucher) }}">Ver</a></td></tr>@empty<tr><td colspan="6" class="text-center text-body-secondary py-4">No hay vales recientes.</td></tr>@endforelse
</tbody></table></div></div></section>
@endif

@if (isset($recentManualVouchers))
<section aria-labelledby="recent-manual-vouchers">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="h5 mb-0" id="recent-manual-vouchers">Vales manuales recientes</h3>
        @if ($dashboardRole === \App\Enums\UserRole::WAREHOUSE_MANAGER && $assignedWarehouses->count() === 1)
            <a class="btn btn-sm btn-outline-primary" href="{{ route('warehouses.outbounds.index', $assignedWarehouses->first()) }}">Ver todas las salidas</a>
        @endif
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Folio</th><th>Fecha</th><th>Motivo</th><th>Destino</th><th>Almacén</th><th>Productos</th><th></th></tr></thead>
                <tbody>
                @forelse ($recentManualVouchers as $voucher)
                    <tr>
                        <td>#{{ $voucher->id }}</td>
                        <td>{{ $voucher->processed_at->format('d/m/Y H:i') }}</td>
                        <td><span class="badge text-bg-secondary">{{ $voucher->reason->label() }}</span></td>
                        <td>
                            @if ($voucher->reason === \App\Enums\InventoryOutboundReason::PATIENT_CARE)
                                <span class="d-block">{{ $voucher->patient_name }}</span>
                                @if ($voucher->room_number)<small class="text-body-secondary">Habitación {{ $voucher->room_number }}</small>@endif
                            @else
                                {{ $voucher->requesting_area ?: 'Consumo del almacén' }}
                            @endif
                        </td>
                        <td>{{ $voucher->warehouse->name }}</td>
                        <td>{{ $voucher->items_count }} {{ $voucher->items_count === 1 ? 'producto' : 'productos' }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('warehouses.outbounds.show', [$voucher->warehouse, $voucher]) }}">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">No hay vales manuales recientes de consumo interno o atención a pacientes.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endif
@endsection
