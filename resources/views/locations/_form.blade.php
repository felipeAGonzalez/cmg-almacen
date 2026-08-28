@php($isEditing = isset($location))

<form method="POST" action="{{ $isEditing ? route('warehouses.locations.update', [$warehouse, $location]) : route('warehouses.locations.store', $warehouse) }}" novalidate>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="form-section">
        <p class="small text-body-secondary mb-4">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

        <div class="mb-3">
            <label for="name" class="form-label">Nombre <span class="text-danger" aria-hidden="true">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name', $location->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ej. Estante A" maxlength="255" autofocus required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="description" class="form-label">Descripción <span class="text-body-secondary fw-normal">(Opcional)</span></label>
            <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="5" maxlength="1000" placeholder="Ej. Segundo estante del área de material de curación">{{ old('description', $location->description ?? '') }}</textarea>
            <div class="form-text">Opcional</div>
            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ route('warehouses.locations.index', $warehouse) }}" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $isEditing ? 'Guardar cambios' : 'Guardar ubicación' }}
        </button>
    </div>
</form>
