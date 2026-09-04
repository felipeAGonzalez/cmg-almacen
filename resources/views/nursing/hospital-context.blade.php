@extends('layouts.app')

@section('page-title', 'Acceso desde Hospitalización')

@section('content')
<div class="page-heading">
    <div>
        <nav aria-label="Ruta de navegación">
            <ol class="breadcrumb small mb-2">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
                <li class="breadcrumb-item active" aria-current="page">Contexto hospitalario</li>
            </ol>
        </nav>
        <h2>Acceso desde Hospitalización</h2>
        <p>La sesión de Almacén se inició correctamente desde Hospitalización.</p>
    </div>
</div>

@if (is_array($hospitalContext))
    <section class="admin-card">
        <div class="admin-card-header">
            <h3 class="h6 fw-bold mb-1">Contexto recibido</h3>
            <p class="small text-body-secondary mb-0">Esta información es temporal y será revalidada antes de una futura solicitud.</p>
            ("create", \App\Models\NursingVoucher::class)
                <a href="{{ route("nursing-vouchers.create") }}" class="btn btn-primary mt-3">
                    <i class="bi bi-clipboard2-plus me-2" aria-hidden="true"></i>Crear vale de Enfermería
                </a>

        </div>
        <dl class="row mb-0">
            <dt class="col-sm-4">ID paciente</dt>
            <dd class="col-sm-8"><code>{{ $hospitalContext['patient_id'] }}</code></dd>
            <dt class="col-sm-4">ID hospitalización</dt>
            <dd class="col-sm-8"><code>{{ $hospitalContext['hospitalization_id'] }}</code></dd>
            <dt class="col-sm-4">Habitación</dt>
            <dd class="col-sm-8 mb-0">{{ $hospitalContext['room_number'] }}</dd>
        </dl>
    </section>
@else
    <div class="alert alert-warning" role="alert">No hay un contexto hospitalario disponible en esta sesión.</div>
@endif
@endsection
