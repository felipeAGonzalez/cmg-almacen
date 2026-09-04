@extends('layouts.app')

@section('page-title', 'Detalle de transferencia')

@section('content')
    <div class="page-heading"><div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
            @if(Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>@else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif
            <li class="breadcrumb-item">{{ $warehouse->name }}</li><li class="breadcrumb-item"><a href="{{ route('warehouses.transfers.index', $warehouse) }}">Transferencias</a></li><li class="breadcrumb-item active">Detalle</li>
        </ol></nav>
        <h2>Detalle de transferencia</h2><p>Consulta los productos y lotes utilizados en este movimiento.</p>
    </div><a href="{{ route('warehouses.transfers.index', $warehouse) }}" class="btn btn-light border"><i class="bi bi-arrow-left me-2"></i>Volver a transferencias</a></div>

    <section class="admin-card mb-4"><div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Datos de la transferencia</h3></div><div class="p-4"><div class="row g-4">
        <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Almacén origen</span><strong class="d-block">{{ $warehouse->name }}</strong></div>
        <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Gabinete destino</span><strong class="d-block">{{ $transfer->cabinet->name }}</strong></div>
        <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Fecha y hora</span><strong class="d-block">{{ $transfer->transferred_at->format('d/m/Y H:i') }}</strong></div>
        <div class="col-sm-6 col-lg-3"><span class="entry-detail-label">Realizó</span><strong class="d-block">{{ $transfer->transferredBy->name }} {{ $transfer->transferredBy->last_name_one }}</strong></div>
        @if($transfer->administrationVoucher && Auth::user()->can('view', $transfer->administrationVoucher))<div class="col-12 alert alert-info mb-0">Originado por <a href="{{ route('administration-vouchers.show', $transfer->administrationVoucher) }}">Vale de Administración #{{ $transfer->administrationVoucher->id }}</a></div>@endif
        <div class="col-12"><span class="entry-detail-label">Notas</span><p class="mb-0">{{ $transfer->notes ?: 'Sin notas' }}</p></div>
    </div></div></section>

    <div class="d-grid gap-3">
        @foreach($transfer->items as $item)
            <section class="admin-card overflow-hidden transfer-detail-product">
                <div class="admin-card-header d-flex flex-wrap justify-content-between gap-2"><div><h3 class="h6 fw-bold mb-1">{{ $item->sourceInventoryItem->product->name }}</h3><p class="small text-body-secondary mb-0">Unidad: {{ $item->sourceInventoryItem->product->unit->name }}</p></div><span class="warehouse-badge">Cantidad transferida: {{ rtrim(rtrim($item->requested_quantity, '0'), '.') }}</span></div>
                <div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Lote interno origen</th><th>Lote fabricante</th><th>Caducidad</th><th>Cantidad tomada</th><th>Lote en gabinete</th></tr></thead><tbody>
                    @foreach($item->allocations as $allocation)<tr>
                        <td><code class="entry-lot">{{ $allocation->sourceBatch->internal_lot }}</code></td>
                        <td>{{ $allocation->sourceBatch->manufacturer_lot ?: 'No indicado' }}</td>
                        <td class="text-nowrap">{{ $allocation->sourceBatch->expiration_date?->format('d/m/Y') ?: 'No aplica' }}</td>
                        <td>{{ rtrim(rtrim($allocation->quantity, '0'), '.') }}</td>
                        <td><code class="entry-lot">{{ $allocation->destinationBatch->internal_lot }}</code></td>
                    </tr>@endforeach
                </tbody></table></div>
            </section>
        @endforeach
    </div>
@endsection
