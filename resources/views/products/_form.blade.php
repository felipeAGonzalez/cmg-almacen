@php($isEditing = isset($product))

@if ($units->isEmpty() || $categories->isEmpty() || $brands->isEmpty())
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>Debes registrar al menos una unidad, categoría y marca antes de crear productos.
    </div>
@endif

<form method="POST" action="{{ $isEditing ? route('products.update', $product) : route('products.store') }}" novalidate>
    @csrf
    @if ($isEditing) @method('PUT') @endif

    <div class="form-section">
        <h3 class="h6 fw-bold mb-3">Información general</h3>
        <label for="name" class="form-label">Nombre del producto <span class="text-danger" aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" value="{{ old('name', $product->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Ej. Jeringa desechable 10 ml" maxlength="255" autofocus required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-section border-top pt-4 mt-4">
        <h3 class="h6 fw-bold mb-3">Identificación</h3>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="code" class="form-label">Código interno</label>
                <input type="text" id="code" name="code" value="{{ old('code', $product->code ?? '') }}" class="form-control @error('code') is-invalid @enderror" placeholder="Ej. JER-10ML-001" maxlength="100">
                <div class="form-text">Código asignado internamente por CMG.</div>
                @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-6">
                <label for="barcode" class="form-label">Código de barras</label>
                <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}" class="form-control @error('barcode') is-invalid @enderror" placeholder="Ej. 7501234567890" maxlength="100" inputmode="numeric">
                <div class="form-text">Código comercial impreso en el producto, cuando exista.</div>
                @error('barcode') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
        <p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>Debes capturar al menos un código interno o un código de barras.</p>
    </div>

    <div class="form-section border-top pt-4 mt-4">
        <h3 class="h6 fw-bold mb-3">Clasificación</h3>
        <div class="row g-3">
            <div class="col-lg-4">
                <label for="unit_id" class="form-label">Unidad de medida <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="unit_id" name="unit_id" class="form-select @error('unit_id') is-invalid @enderror" required>
                    <option value="">Selecciona una unidad</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_id', $product->unit_id ?? '') === (string) $unit->id)>{{ $unit->name }}</option>
                    @endforeach
                </select>
                @error('unit_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-4">
                <label for="category_id" class="form-label">Categoría <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
                    <option value="">Selecciona una categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $product->category_id ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-4">
                <label for="brand_id" class="form-label">Marca <span class="text-danger" aria-hidden="true">*</span></label>
                <select id="brand_id" name="brand_id" class="form-select @error('brand_id') is-invalid @enderror" required>
                    <option value="">Selecciona una marca</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id ?? '') === (string) $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
                @error('brand_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>

    <div class="form-section border-top pt-4 mt-4">
        <h3 class="h6 fw-bold mb-3">Información adicional</h3>
        <label for="description" class="form-label">Descripción <span class="text-body-secondary fw-normal">(Opcional)</span></label>
        <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="5" maxlength="2000" placeholder="Describe brevemente el producto">{{ old('description', $product->description ?? '') }}</textarea>
        <div class="form-text">Opcional</div>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="form-actions">
        <a href="{{ route('products.index') }}" class="btn btn-light border">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $isEditing ? 'Guardar cambios' : 'Guardar producto' }}</button>
    </div>
</form>
