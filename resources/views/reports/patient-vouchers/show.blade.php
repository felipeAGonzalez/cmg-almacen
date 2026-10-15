@extends('layouts.app')

@section('page-title', 'Detalle de vales del paciente')

@section('content')
<div class="page-heading">
    <div>
        <nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ route('reports.patient-vouchers.index', $filters) }}">Concentrado de vales</a></li><li class="breadcrumb-item active">Paciente</li></ol></nav>
        <h2>{{ $patientName }}</h2>
        <p>Vales del {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}.</p>
    </div>
    <a href="{{ route('reports.patient-vouchers.index', $filters) }}" class="btn btn-light border"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<div class="accordion" id="patient-vouchers-accordion">
    <section class="accordion-item" aria-labelledby="consolidated-title">
        <h3 class="accordion-header" id="consolidated-title">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#consolidated-vouchers" aria-expanded="true" aria-controls="consolidated-vouchers">
                <span>
                    <strong>Vales consolidados</strong>
                    <span class="d-block small fw-normal text-body-secondary">Totales acumulados para facilitar el cobro.</span>
                </span>
            </button>
        </h3>
        <div id="consolidated-vouchers" class="accordion-collapse collapse show" data-bs-parent="#patient-vouchers-accordion">
            <div class="accordion-body p-0">
                <p class="small text-body-secondary px-4 pt-3 mb-3">La cantidad neta corresponde a lo entregado menos las devoluciones recibidas.</p>
                <div class="table-responsive">
                    <table class="table admin-table align-middle mb-0">
                        <thead><tr><th>Producto</th><th>Solicitado</th><th>Entregado</th><th>Devuelto</th><th>Neto para cobro</th><th>Unidad</th></tr></thead>
                        <tbody>
                            @foreach($consolidatedProducts as $product)
                                <tr>
                                    <td><strong>{{ $product['name'] }}</strong></td>
                                    <td>{{ \App\Models\InventoryItem::formatQuantity($product['requested']) }}</td>
                                    <td>{{ \App\Models\InventoryItem::formatQuantity($product['supplied']) }}</td>
                                    <td>{{ \App\Models\InventoryItem::formatQuantity($product['returned']) }}</td>
                                    <td><span class="badge text-bg-primary fs-6">{{ \App\Models\InventoryItem::formatQuantity($product['net']) }}</span></td>
                                    <td>{{ $product['unit'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="accordion-item" aria-labelledby="individual-vouchers-title">
        <h3 class="accordion-header" id="individual-vouchers-title">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#individual-vouchers" aria-expanded="false" aria-controls="individual-vouchers">
                <span class="d-flex align-items-center gap-2">
                    <strong>Vales por separado</strong>
                    <span class="badge text-bg-light border">{{ $vouchers->count() }} {{ $vouchers->count() === 1 ? 'vale' : 'vales' }}</span>
                </span>
            </button>
        </h3>
        <div id="individual-vouchers" class="accordion-collapse collapse" data-bs-parent="#patient-vouchers-accordion">
            <div class="accordion-body">
                <div class="d-grid gap-3">
                    @foreach($vouchers as $voucher)
                        <article class="admin-card overflow-hidden">
                            <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                                <div>
                                    <h4 class="h6 fw-bold mb-1">{{ $voucher['type_label'] }} #{{ $voucher['id'] }}</h4>
                                    <p class="small text-body-secondary mb-0">{{ $voucher['occurred_at']->format('d/m/Y H:i') }} · {{ $voucher['warehouse'] }} · {{ $voucher['source'] }}</p>
                                </div>
                                <div class="d-flex align-items-center gap-2"><span class="badge {{ $voucher['status_class'] }}">{{ $voucher['status_label'] }}</span><a href="{{ $voucher['url'] }}" class="btn btn-sm btn-outline-primary">Abrir vale</a></div>
                            </div>
                            <div class="px-4 py-3 border-bottom"><span class="entry-detail-label">Habitación</span><strong class="d-block">{{ $voucher['room_number'] ?: 'No indicada' }}</strong></div>
                            <div class="table-responsive">
                                <table class="table admin-table align-middle mb-0">
                                    <thead><tr><th>Producto</th><th>Solicitado</th><th>Entregado</th><th>Devuelto</th><th>Neto</th><th>Unidad</th></tr></thead>
                                    <tbody>
                                        @foreach($voucher['products'] as $product)
                                            <tr><td>{{ $product['name'] }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($product['requested']) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($product['supplied']) }}</td><td>{{ \App\Models\InventoryItem::formatQuantity($product['returned']) }}</td><td class="fw-semibold">{{ \App\Models\InventoryItem::formatQuantity($product['net']) }}</td><td>{{ $product['unit'] }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
