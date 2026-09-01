@extends('layouts.app')
@section('page-title', 'Integración con Hospitalización')
@section('content')
<div class="page-heading"><div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li><li class="breadcrumb-item">Configuración</li><li class="breadcrumb-item active">Integración hospitalaria</li></ol></nav><h2>Integración con Hospitalización</h2><p>Pacientes hospitalizados actualmente</p></div></div>
@if($integrationError)
<div class="alert alert-danger shadow-sm" role="alert"><h3 class="h6 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>No fue posible consultar los pacientes hospitalizados.</h3><p class="mb-0">{{ $integrationError }}</p></div>
@else
<section class="admin-card overflow-hidden"><div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Pacientes activos</h3><p class="small text-body-secondary mb-0">Consulta diagnóstica en tiempo real del sistema de Hospitalización.</p></div>
@if($patients->isEmpty())<div class="empty-state"><span class="empty-state-icon"><i class="bi bi-hospital"></i></span><h3 class="h5">No hay pacientes hospitalizados actualmente.</h3></div>
@else<div class="table-responsive"><table class="table admin-table align-middle"><thead><tr><th>Habitación</th><th>Paciente</th><th>ID paciente</th><th>ID hospitalización</th></tr></thead><tbody>@foreach($patients as $patient)<tr><td><span class="badge text-bg-light border">{{ $patient->roomNumber }}</span></td><td><strong>{{ $patient->patientName }}</strong></td><td><code>{{ $patient->externalPatientId }}</code></td><td><code>{{ $patient->externalHospitalizationId }}</code></td></tr>@endforeach</tbody></table></div>@endif
</section>
@endif
@endsection
