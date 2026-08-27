@php($isEditing = isset($unit))

<form method="POST" action="{{ $isEditing ? route('units.update', $unit) : route('units.store') }}" novalidate>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="form-section">
        <p class="small text-body-secondary mb-4">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

        <div class="mb-3">
            <label for="name" class="form-label">Nombre <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name', $unit->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ej. Pieza" maxlength="255" autofocus required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="abbreviation" class="form-label">Abreviatura <span class="text-body-secondary fw-normal">(Opcional)</span></label>
            <input type="text" id="abbreviation" name="abbreviation" value="{{ old('abbreviation', $unit->abbreviation ?? '') }}" class="form-control @error('abbreviation') is-invalid @enderror" placeholder="Ej. PZ" maxlength="20">
            <div class="form-text">Opcional</div>
            @error('abbreviation')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ route('units.index') }}" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $isEditing ? 'Guardar cambios' : 'Guardar unidad' }}
        </button>
    </div>
</form>
