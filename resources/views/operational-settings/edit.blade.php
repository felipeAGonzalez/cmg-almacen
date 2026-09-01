@extends('layouts.app')
@section('page-title', 'Horario operativo')
@section('content')
<div class="page-heading"><div><nav aria-label="Ruta de navegación"><ol class="breadcrumb small mb-2"><li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li><li class="breadcrumb-item">Configuración</li><li class="breadcrumb-item active">Horario operativo</li></ol></nav><h2>Horario operativo</h2><p>Define el horario en que los vales de Enfermería serán atendidos desde el almacén principal.</p></div></div>
<div class="alert alert-info border-0 shadow-sm" role="note"><i class="bi bi-info-circle-fill me-2" aria-hidden="true"></i>Dentro de este horario, los futuros vales de Enfermería se surtirán desde el almacén principal. Fuera de este horario, se utilizará el gabinete correspondiente.</div>
<form method="POST" action="{{ route('operational-settings.update') }}" class="admin-card mx-auto" style="max-width:760px">@csrf @method('PUT')
<div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Ventana de atención</h3><p class="small text-body-secondary mb-0">Horario predeterminado: 09:00 a 17:00</p></div>
<div class="p-3 p-md-4"><div class="row g-3">
<div class="col-md-6"><label for="warehouse_service_start_time" class="form-label">Inicio de atención en almacén</label><input type="time" id="warehouse_service_start_time" name="warehouse_service_start_time" value="{{ old('warehouse_service_start_time', substr($setting->warehouse_service_start_time, 0, 5)) }}" class="form-control @error('warehouse_service_start_time') is-invalid @enderror" required>@error('warehouse_service_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-6"><label for="warehouse_service_end_time" class="form-label">Fin de atención en almacén</label><input type="time" id="warehouse_service_end_time" name="warehouse_service_end_time" value="{{ old('warehouse_service_end_time', substr($setting->warehouse_service_end_time, 0, 5)) }}" class="form-control @error('warehouse_service_end_time') is-invalid @enderror" required>@error('warehouse_service_end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
</div>
@php($shownStart = old('warehouse_service_start_time', substr($setting->warehouse_service_start_time, 0, 5)))
@php($shownEnd = old('warehouse_service_end_time', substr($setting->warehouse_service_end_time, 0, 5)))
@if($shownStart > $shownEnd)<div class="alert alert-warning mt-4 mb-0" role="note"><i class="bi bi-moon-stars me-2" aria-hidden="true"></i>El horario cruza la medianoche.</div>@endif
<p class="small text-body-secondary mt-4 mb-0">La hora final es exclusiva: al llegar a esa hora, el almacén se considera fuera de servicio para el surtido automático futuro.</p>
<div class="d-flex justify-content-end mt-4"><button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Guardar horario</button></div>
</div></form>
@endsection
