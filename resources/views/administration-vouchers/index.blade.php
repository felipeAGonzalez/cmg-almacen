@extends('layouts.app')
@section('page-title', 'Vales de Administración')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h2 class="mb-1">Vales de Administración</h2>
        <p class="text-body-secondary mb-0">Solicitudes de reposición de gabinetes.</p>
    </div>
    @can('create', \App\Models\AdministrationVoucher::class)
        <a href="{{ route('administration-vouchers.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Crear vale</span>
        </a>
    @endcan
</div>
@can('create', \App\Models\AdministrationVoucher::class)
@if($pendingConsumptions->isNotEmpty())
<section class="card border-0 shadow-sm mb-4" aria-labelledby="cabinetConsumptionTitle">
    <div class="card-header bg-white py-3">
        <h3 class="h6 fw-bold mb-1" id="cabinetConsumptionTitle">Consumo de gabinetes pendiente de reposición</h3>
        <p class="small text-body-secondary mb-0">Genera un vale con las salidas netas del gabinete. Las devoluciones ya recibidas se descuentan automáticamente.</p>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Fecha de consumo</th><th>Almacén</th><th>Gabinete</th><th>Productos</th><th>Detalle</th><th class="text-end">Acción</th></tr></thead>
            <tbody>
            @foreach($pendingConsumptions as $group)
                <tr>
                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($group['consumption_date'])->format('d/m/Y') }}</td>
                    <td>{{ $group['warehouse_name'] }}</td>
                    <td><strong>{{ $group['cabinet_name'] }}</strong></td>
                    <td>{{ $group['total_products'] }}</td>
                    <td><ul class="small mb-0 ps-3">@foreach($group['products'] as $product)<li>{{ $product['name'] }}: {{ \App\Models\InventoryItem::formatQuantity($product['quantity']) }}</li>@endforeach</ul></td>
                    <td class="text-end">
                        @if($group['can_generate'])
                            <form method="POST" action="{{ route('administration-vouchers.consumptions.store') }}">@csrf
                                <input type="hidden" name="cabinet_id" value="{{ $group['cabinet_id'] }}">
                                <input type="hidden" name="consumption_date" value="{{ $group['consumption_date'] }}">
                                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Generar vale</button>
                            </form>
                        @else
                            <span class="badge text-bg-warning">Falta configurar un producto en el almacén</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif
@endcan
<form class="card card-body border-0 shadow-sm mb-4" method="GET"><div class="row g-3"><div class="col-md-2"><label class="form-label">Estado</label><select name="status" class="form-select"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ $status->label() }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Almacén</label><select name="warehouse_id" class="form-select"><option value="">Todos</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)request('warehouse_id')===(string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Gabinete</label><select name="cabinet_id" class="form-select"><option value="">Todos</option>@foreach($cabinets as $cabinet)<option value="{{ $cabinet->id }}" @selected((string)request('cabinet_id')===(string)$cabinet->id)>{{ $cabinet->name }}</option>@endforeach</select></div><div class="col-md-2"><label class="form-label">Desde</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Hasta</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div><div class="col-12 d-flex gap-2"><button class="btn btn-primary">Filtrar</button><a href="{{ route('administration-vouchers.index') }}" class="btn btn-outline-secondary">Limpiar</a></div></div></form>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Folio</th><th>Fecha</th><th>Almacén</th><th>Gabinete</th><th>Solicitó</th><th>Estado</th><th>Acción</th></tr></thead><tbody>@forelse($vouchers as $voucher)<tr><td>#{{ $voucher->id }}</td><td>{{ $voucher->requested_at->format('d/m/Y H:i') }}</td><td>{{ $voucher->warehouse->name }}</td><td>{{ $voucher->cabinet->name }}</td><td>{{ $voucher->requester->name }} {{ $voucher->requester->last_name_one }}</td><td><span class="badge {{ $voucher->status->badgeClass() }}">{{ $voucher->status->label() }}</span></td><td><a href="{{ route('administration-vouchers.show',$voucher) }}" class="btn btn-sm btn-outline-primary">Ver detalle</a></td></tr>@empty<tr><td colspan="7" class="text-center py-5 text-body-secondary">No hay vales de Administración registrados.</td></tr>@endforelse</tbody></table></div>@if($vouchers->hasPages())<div class="card-footer bg-white">{{ $vouchers->links() }}</div>@endif</div>
@endsection
