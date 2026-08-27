@php($isEditing = isset($supplier))

<form method="POST" action="{{ $isEditing ? route('warehouses.suppliers.update', [$warehouse, $supplier]) : route('warehouses.suppliers.store', $warehouse) }}" novalidate>
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="form-section">
        <p class="small text-body-secondary mb-4">Los campos marcados con <span class="text-danger">*</span> son obligatorios.</p>
        <div class="row g-3">
            <div class="col-12">
                <label for="name" class="form-label">Nombre del proveedor <span class="text-danger" aria-hidden="true">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $supplier->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ej. Distribuidora Médica del Centro" maxlength="255" autocomplete="organization" autofocus required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="contact_name" class="form-label">Nombre de contacto</label>
                <input type="text" id="contact_name" name="contact_name" value="{{ old('contact_name', $supplier->contact_name ?? '') }}" class="form-control @error('contact_name') is-invalid @enderror" placeholder="Ej. Juan Pérez" maxlength="255" autocomplete="name">
                @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-6">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $supplier->phone ?? '') }}" class="form-control @error('phone') is-invalid @enderror" placeholder="Ej. 443 000 0000" maxlength="50" autocomplete="tel">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" id="email" name="email" value="{{ old('email', $supplier->email ?? '') }}" class="form-control @error('email') is-invalid @enderror" placeholder="Ej. contacto@proveedor.com" maxlength="255" autocomplete="email">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="address" class="form-label">Dirección</label>
                <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3" maxlength="1000" placeholder="Ej. Av. Principal 123" autocomplete="street-address">{{ old('address', $supplier->address ?? '') }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="notes" class="form-label">Notas</label>
                <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="4" placeholder="Agrega observaciones breves sobre el proveedor.">{{ old('notes', $supplier->notes ?? '') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ route('warehouses.suppliers.index', $warehouse) }}" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $isEditing ? 'Guardar cambios' : 'Guardar proveedor' }}</button>
    </div>
</form>
