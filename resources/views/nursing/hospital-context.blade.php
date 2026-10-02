@extends('layouts.app')

@section('page-title', 'Paciente seleccionado')

@section('content')
<div class="page-heading">
    <div>
        <nav aria-label="Ruta de navegación">
            <ol class="breadcrumb small mb-2">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Paciente seleccionado</li>
            </ol>
        </nav>
        <h2>Paciente seleccionado desde Hospitalización</h2>
        <p>La sesión se inició correctamente desde Hospitalización. Ya puedes crear un vale de Enfermería para este paciente.</p>
    </div>
</div>

@if (isset($contextError))
    <div class="alert alert-warning shadow-sm" role="alert">
        <h3 class="h6 fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>No es posible preparar el vale</h3>
        <p class="mb-0">{{ $contextError }}</p>
    </div>
    <a href="{{ route('home') }}" class="btn btn-light border"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Volver al inicio</a>
@elseif (isset($hospitalization, $source))
    <div class="alert alert-success border-0 shadow-sm d-flex gap-3 align-items-start" role="status">
        <i class="bi bi-check-circle-fill fs-4" aria-hidden="true"></i>
        <div>
            <h3 class="h6 fw-bold mb-1">Acceso desde Hospitalización confirmado</h3>
            <p class="mb-0">La información del paciente fue recibida correctamente y será validada nuevamente al crear el vale.</p>
        </div>
    </div>

    <section class="admin-card overflow-hidden" aria-labelledby="selected-patient-title">
        <div class="admin-card-header">
            <span class="entry-detail-label">Paciente</span>
            <h3 class="h4 mb-1" id="selected-patient-title">{{ $hospitalization->patientName }}</h3>
            <p class="mb-0 text-body-secondary"><i class="bi bi-door-open me-2" aria-hidden="true"></i>Habitación {{ $hospitalization->roomNumber }}</p>
        </div>

        <div class="p-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <span class="entry-detail-label">Almacén asignado</span>
                    <div class="fw-semibold fs-5">{{ $source->warehouse->name }}</div>
                </div>
                <div class="col-md-6">
                    <span class="entry-detail-label">Origen de surtido</span>
                    @if ($source->type === \App\Enums\NursingSupplySourceType::WAREHOUSE)
                        <div class="fw-semibold fs-5"><span class="badge text-bg-primary me-2">Almacén</span>{{ $source->warehouse->name }}</div>
                        <p class="small text-body-secondary mb-0 mt-1">Horario operativo vigente.</p>
                    @else
                        <div class="fw-semibold fs-5"><span class="badge text-bg-info me-2">Gabinete de Enfermería</span>{{ $source->cabinet->name }}</div>
                        <p class="small text-body-secondary mb-0 mt-1">El almacén se encuentra fuera de horario operativo o en día de descanso.</p>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mt-4 pt-4 border-top">
                @can('create', \App\Models\NursingVoucher::class)
                    <a href="{{ route('nursing-vouchers.create') }}" class="btn btn-primary btn-lg">
                        <i class="bi bi-clipboard2-plus me-2" aria-hidden="true"></i>Crear vale de Enfermería
                    </a>
                @endcan
                <a href="{{ route('nursing-vouchers.index') }}" class="btn btn-light border">Ver mis vales</a>
            </div>

            <details class="mt-4 pt-3 border-top">
                <summary class="fw-semibold text-body-secondary">Ver datos técnicos</summary>
                <dl class="row small mt-3 mb-0">
                    <dt class="col-sm-4">ID paciente</dt>
                    <dd class="col-sm-8"><code>{{ $hospitalContext['patient_id'] }}</code></dd>
                    <dt class="col-sm-4">ID hospitalización</dt>
                    <dd class="col-sm-8"><code>{{ $hospitalContext['hospitalization_id'] }}</code></dd>
                    <dt class="col-sm-4">ID habitación</dt>
                    <dd class="col-sm-8 mb-0"><code>{{ $hospitalContext['room_id'] }}</code></dd>
                </dl>
            </details>
        </div>
    </section>
@endif
@endsection
