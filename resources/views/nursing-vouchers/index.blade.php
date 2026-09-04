@extends('layouts.app')

@section('page-title', 'Vales de Enfermería')

@section('content')
<div class="page-heading">
    <div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li><li class="breadcrumb-item active">Vales de Enfermería</li></ol></nav><h2>Vales de Enfermería</h2><p>Consulta y atiende solicitudes de insumos para pacientes hospitalizados.</p></div>
    @can('create', \App\Models\NursingVoucher::class)
        <a href="{{ route('nursing-vouchers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Crear vale</a>
    @endcan
</div>

<section class="admin-card mb-4">
    <div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Filtros</h3></div>
    <form method="GET" action="{{ route('nursing-vouchers.index') }}" class="p-3">
        <div class="row g-3 align-items-end">
            <div class="col-sm-6 col-lg-3"><label for="status" class="form-label">Estado</label><select id="status" name="status" class="form-select"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-lg-3"><label for="date_from" class="form-label">Fecha desde</label><input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control @error('date_from') is-invalid @enderror">@error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-sm-6 col-lg-3"><label for="date_to" class="form-label">Fecha hasta</label><input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control @error('date_to') is-invalid @enderror">@error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @if(Auth::user()->isAdmin())<div class="col-sm-6 col-lg-3"><label for="warehouse_id" class="form-label">Almacén</label><select id="warehouse_id" name="warehouse_id" class="form-select"><option value="">Todos</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)request('warehouse_id') === (string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>@endif
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-2"></i>Filtrar</button><a class="btn btn-light border" href="{{ route('nursing-vouchers.index') }}">Limpiar filtros</a></div>
        </div>
    </form>
</section>

<section class="admin-card overflow-hidden">
    @if($vouchers->isEmpty())
        <div class="empty-state"><i class="bi bi-clipboard2-pulse" aria-hidden="true"></i><h3>No hay vales de Enfermería para los filtros seleccionados.</h3><p>Los vales disponibles aparecerán aquí según tu perfil y almacenes asignados.</p></div>
    @else
        <div class="table-responsive"><table class="table admin-table align-middle mb-0"><thead><tr><th>Folio</th><th>Fecha</th><th>Paciente</th><th>Habitación</th><th>Origen</th><th>Solicitó</th><th>Estado</th><th class="text-end">Acción</th></tr></thead><tbody>
        @foreach($vouchers as $voucher)<tr>
            <td class="fw-semibold">#{{ $voucher->id }}</td>
            <td class="text-nowrap">{{ $voucher->requested_at->format('d/m/Y H:i') }}</td>
            <td>{{ $voucher->patient_name }}</td><td>{{ $voucher->room_number }}</td>
            <td>@if($voucher->source_type === \App\Enums\NursingSupplySourceType::WAREHOUSE)<span class="badge text-bg-primary">Almacén</span><small class="d-block text-body-secondary mt-1">{{ $voucher->warehouse->name }}</small>@else<span class="badge text-bg-info">Gabinete</span><small class="d-block text-body-secondary mt-1">{{ $voucher->sourceCabinet->name }}</small>@endif</td>
            <td>{{ $voucher->requester->name }} {{ $voucher->requester->last_name_one }}</td>
            <td><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span></td>
            <td class="text-end"><a href="{{ route('nursing-vouchers.show', $voucher) }}" class="btn btn-sm btn-outline-primary">Ver detalle</a></td>
        </tr>@endforeach
        </tbody></table></div>
        @if($vouchers->hasPages())<div class="p-3 border-top">{{ $vouchers->links() }}</div>@endif
    @endif
</section>
@endsection
