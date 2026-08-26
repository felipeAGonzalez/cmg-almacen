@php($isEditing = isset($warehouse))

<form method="POST" action="{{ $isEditing ? route('warehouses.update', $warehouse) : route('warehouses.store') }}" novalidate>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="form-section">
        <p class="small text-body-secondary mb-4">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>

        <label for="name" class="form-label">Nombre del almacén <span class="text-danger" aria-hidden="true">*</span></label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $warehouse->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="Ej. Almacén Centro"
            maxlength="255"
            autocomplete="organization"
            autofocus
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-actions">
        <a href="{{ route('warehouses.index') }}" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $isEditing ? 'Guardar cambios' : 'Guardar almacén' }}
        </button>
    </div>
</form>
