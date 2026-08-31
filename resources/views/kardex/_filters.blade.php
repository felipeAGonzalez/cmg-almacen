<section class="admin-card mb-4">
    <div class="admin-card-header"><h3 class="h6 fw-bold mb-0">Filtros</h3></div>
    <form method="GET" action="{{ $kardexRoute }}" class="p-3 p-lg-4">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label for="inventory_item_id" class="form-label">Producto</label>
                <select id="inventory_item_id" name="inventory_item_id" class="form-select @error('inventory_item_id') is-invalid @enderror">
                    <option value="">Todos los productos</option>
                    @foreach($inventoryItems as $inventoryItem)
                        <option value="{{ $inventoryItem->id }}" @selected((string) ($filters['inventory_item_id'] ?? '') === (string) $inventoryItem->id)>{{ $inventoryItem->product->name }} — {{ $inventoryItem->product->unit->name }}</option>
                    @endforeach
                </select>
                @error('inventory_item_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="movement_type" class="form-label">Movimiento</label>
                <select id="movement_type" name="movement_type" class="form-select @error('movement_type') is-invalid @enderror">
                    <option value="">Todos los movimientos</option>
                    @foreach($movementTypes as $value => $label)<option value="{{ $value }}" @selected(($filters['movement_type'] ?? '') === $value)>{{ $label }}</option>@endforeach
                </select>
                @error('movement_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="date_from" class="form-label">Desde</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="form-control @error('date_from') is-invalid @enderror">
                @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="date_to" class="form-label">Hasta</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="form-control @error('date_to') is-invalid @enderror">
                @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-lg-1 d-grid"><button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button></div>
        </div>
        <div class="mt-3"><a href="{{ $kardexRoute }}" class="btn btn-sm btn-light border">Limpiar filtros</a></div>
    </form>
</section>
