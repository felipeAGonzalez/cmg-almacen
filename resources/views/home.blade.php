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
        @if (isset($summary['critical_stock']))
            <div class="col-6 col-xl-3"><div class="card border-danger border-start border-4 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-semibold text-danger">{{ $summary['critical_stock'] }}</div><div>Stock crítico</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card border-warning border-start border-4 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-semibold">{{ $summary['low_stock'] }}</div><div>Stock bajo</div></div></div></div>
            <div class="col-6 col-xl-3"><div class="card border-secondary border-start border-4 shadow-sm h-100"><div class="card-body"><div class="fs-3 fw-semibold">{{ $summary['expired_batches'] }}</div><div>Lotes vencidos con existencia</div></div></div></div>
        @endif
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
<section aria-labelledby="recent-administration"><h3 class="h5 mb-3" id="recent-administration">Actividad reciente de Administración</h3>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Folio</th><th>Almacén</th><th>Gabinete</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>
@forelse ($recentAdministrationVouchers as $voucher)<tr><td>#{{ $voucher->id }}</td><td>{{ $voucher->warehouse->name }}</td><td>{{ $voucher->cabinet->name }}</td><td><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span></td><td>{{ $voucher->requested_at->format('d/m/Y H:i') }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('administration-vouchers.show', $voucher) }}">Ver</a></td></tr>@empty<tr><td colspan="6" class="text-center text-body-secondary py-4">No hay vales recientes.</td></tr>@endforelse
</tbody></table></div></div></section>
@endif
@endsection
