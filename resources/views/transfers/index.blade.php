@extends('layouts.app')

@section('page-title', 'Transferencias')

@section('content')
    <div class="page-heading">
        <div>
            <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2">
                @if(Auth::user()->isAdmin())<li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">Almacenes</a></li>
                @else<li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>@endif
                <li class="breadcrumb-item">{{ $warehouse->name }}</li><li class="breadcrumb-item active">Transferencias</li>
            </ol></nav>
            <h2>Transferencias</h2>
            <p>Consulta los movimientos de mercancía del almacén hacia sus gabinetes.</p>
            <span class="badge text-bg-light border"><i class="bi bi-building me-1"></i>Almacén: {{ $warehouse->name }}</span>
        </div>
        <a href="{{ route('warehouses.transfers.create', $warehouse) }}" class="btn btn-primary"><i class="bi bi-arrow-left-right me-2"></i>Nueva transferencia</a>
    </div>

    <section class="admin-card overflow-hidden">
        <div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Transferencias registradas</h3><p class="small text-body-secondary mb-0">{{ $transfers->total() }} {{ $transfers->total() === 1 ? 'transferencia' : 'transferencias' }}</p></div>
        @if($transfers->isEmpty())
            <div class="empty-state"><span class="empty-state-icon"><i class="bi bi-arrow-left-right"></i></span><h3 class="h5">No hay transferencias registradas en este almacén.</h3><p class="text-body-secondary mb-4">Registra una transferencia cuando surtas mercancía a un gabinete.</p><a href="{{ route('warehouses.transfers.create', $warehouse) }}" class="btn btn-primary">Nueva transferencia</a></div>
        @else
            <div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Fecha</th><th>Gabinete</th><th>Productos</th><th>Realizó</th><th class="text-end">Acciones</th></tr></thead><tbody>
                @foreach($transfers as $transfer)<tr>
                    <td class="text-nowrap">{{ $transfer->transferred_at->format('d/m/Y H:i') }}</td>
                    <td><strong>{{ $transfer->cabinet->name }}</strong></td>
                    <td>{{ $transfer->items_count }} {{ $transfer->items_count === 1 ? 'producto' : 'productos' }}</td>
                    <td>{{ $transfer->transferredBy->name }} {{ $transfer->transferredBy->last_name_one }}</td>
                    <td class="text-end"><a href="{{ route('warehouses.transfers.show', [$warehouse, $transfer]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver detalle</a></td>
                </tr>@endforeach
            </tbody></table></div>
            @if($transfers->hasPages())<div class="border-top px-3 py-3">{{ $transfers->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
        @endif
    </section>
@endsection
