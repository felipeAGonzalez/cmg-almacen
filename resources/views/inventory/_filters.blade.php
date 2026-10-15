<form method="GET" action="{{ $filterAction }}" class="admin-card mb-4" aria-label="Filtros de inventario">
    <div class="p-3 p-lg-4">
        <div class="mb-3">
            <h3 class="h6 fw-bold mb-1">Filtrar existencias</h3>
            <p class="small text-body-secondary mb-0">Busca productos y consulta su estado de stock o caducidad.</p>
        </div>
        <div class="row g-3 align-items-end">
            @isset($inventoryContexts)
                <div class="col-12 col-lg-4">
                    <label for="inventory-scope" class="form-label">Inventario</label>
                    <select class="form-select @error('inventory_scope') is-invalid @enderror" id="inventory-scope" name="inventory_scope">
                        <option value="">Todos los almacenes y gabinetes</option>
                        @foreach($inventoryContexts as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['inventory_scope'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('inventory_scope')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endisset
            <div class="col-12 {{ isset($inventoryContexts) ? 'col-lg-4' : 'col-lg-6' }}">
                <label for="inventory-search" class="form-label">Producto</label>
                <input type="search" class="form-control" id="inventory-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre, código o código de barras">
            </div>
            <div class="col-12 col-sm-6 {{ isset($inventoryContexts) ? 'col-lg-2' : 'col-lg-3' }}">
                <label for="stock-status" class="form-label">Estado de stock</label>
                <select class="form-select" id="stock-status" name="stock_status">
                    <option value="">Todos</option>
                    <option value="critical" @selected(($filters['stock_status'] ?? '') === 'critical')>Crítico</option>
                    <option value="low" @selected(($filters['stock_status'] ?? '') === 'low')>Bajo</option>
                    <option value="healthy" @selected(($filters['stock_status'] ?? '') === 'healthy')>Correcto</option>
                    <option value="without_expiration" @selected(($filters['stock_status'] ?? '') === 'without_expiration')>Sin caducidad</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 {{ isset($inventoryContexts) ? 'col-lg-2' : 'col-lg-3' }}">
                <label for="expiration-status" class="form-label">Caducidad</label>
                <select class="form-select" id="expiration-status" name="expiration_status">
                    <option value="">Todas</option>
                    <option value="expired" @selected(($filters['expiration_status'] ?? '') === 'expired')>Vencidos con existencia</option>
                    <option value="valid" @selected(($filters['expiration_status'] ?? '') === 'valid')>Con caducidad vigente</option>
                    <option value="without_expiration" @selected(($filters['expiration_status'] ?? '') === 'without_expiration')>Sin caducidad</option>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label for="expiration-from" class="form-label">Caducidad desde</label>
                <input type="date" class="form-control" id="expiration-from" name="expiration_from" value="{{ $filters['expiration_from'] ?? '' }}">
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <label for="expiration-to" class="form-label">Caducidad hasta</label>
                <input type="date" class="form-control" id="expiration-to" name="expiration_to" value="{{ $filters['expiration_to'] ?? '' }}">
            </div>
            <div class="col-12 col-lg-6 d-flex flex-wrap justify-content-lg-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1" aria-hidden="true"></i>Filtrar</button>
                <a href="{{ $filterAction }}" class="btn btn-light border">Limpiar filtros</a>
            </div>
        </div>
        @error('expiration_to')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </div>
</form>
