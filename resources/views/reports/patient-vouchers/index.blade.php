@extends('layouts.app')

@section('page-title', 'Concentrado de vales')

@section('content')
<div class="page-heading">
    <div>
        <h2>Concentrado de vales por paciente</h2>
        <p>Consulta vales de Enfermería y vales manuales para preparar su recolección de cobro.</p>
    </div>
</div>

<section class="admin-card mb-4">
    <div class="admin-card-header">
        <h3 class="h6 fw-bold mb-1">Periodo</h3>
        <p class="small text-body-secondary mb-0">Por defecto se muestra la actividad del día actual.</p>
    </div>
    <div class="p-4">
        <form method="GET" action="{{ route('reports.patient-vouchers.index') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="date_from" class="form-label">Fecha desde</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}" class="form-control @error('date_from') is-invalid @enderror" required>
                @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="date_to" class="form-label">Fecha hasta</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}" class="form-control @error('date_to') is-invalid @enderror" required>
                @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel me-1"></i>Consultar</button>
                <a href="{{ route('reports.patient-vouchers.index') }}" class="btn btn-light border">Hoy</a>
            </div>
        </form>
    </div>
</section>

<section class="admin-card overflow-hidden" aria-labelledby="patients-title">
    <div class="admin-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="h6 fw-bold mb-1" id="patients-title">Pacientes con vales</h3>
            <p class="small text-body-secondary mb-0">{{ $patients->total() }} {{ $patients->total() === 1 ? 'paciente encontrado' : 'pacientes encontrados' }}</p>
        </div>
        <span class="badge text-bg-light border">{{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d/m/Y') }} — {{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</span>
    </div>
    @if($patients->isEmpty())
        <div class="empty-state">
            <span class="empty-state-icon"><i class="bi bi-person-vcard"></i></span>
            <h3 class="h5">No hay vales para este periodo.</h3>
            <p class="text-body-secondary mb-0">Cambia las fechas para consultar otro periodo.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table admin-table align-middle mb-0">
                <thead><tr><th>Paciente</th><th>Último vale</th><th>Enfermería</th><th>Manuales</th><th>Total</th><th class="text-end">Acción</th></tr></thead>
                <tbody>
                    @foreach($patients as $patient)
                        <tr>
                            <td><strong>{{ $patient->patient_name }}</strong></td>
                            <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($patient->last_voucher_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $patient->nursing_count }}</td>
                            <td>{{ $patient->manual_count }}</td>
                            <td><span class="badge text-bg-primary">{{ $patient->vouchers_count }}</span></td>
                            <td class="text-end"><a href="{{ $patient->detail_url }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver detalle</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($patients->hasPages())<div class="border-top px-3 py-3">{{ $patients->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
    @endif
</section>
@endsection
