<div class="border rounded p-3 h-100">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <strong>{{ $name }}</strong>
        <a href="{{ route('inventory.index', ['inventory_scope' => $scope]) }}" class="small text-decoration-none">Ver todo</a>
    </div>
    <div class="row g-2 text-center">
        <div class="col-4"><a href="{{ route('inventory.index', ['inventory_scope' => $scope, 'stock_status' => 'critical']) }}" class="d-block rounded bg-danger-subtle p-2 text-decoration-none text-body"><span class="fs-4 fw-semibold text-danger d-block">{{ $counts['critical_stock'] }}</span><small>Stock crítico</small></a></div>
        <div class="col-4"><a href="{{ route('inventory.index', ['inventory_scope' => $scope, 'stock_status' => 'low']) }}" class="d-block rounded bg-warning-subtle p-2 text-decoration-none text-body"><span class="fs-4 fw-semibold d-block">{{ $counts['low_stock'] }}</span><small>Stock bajo</small></a></div>
        <div class="col-4"><a href="{{ route('inventory.index', ['inventory_scope' => $scope, 'expiration_status' => 'expired']) }}" class="d-block rounded bg-secondary-subtle p-2 text-decoration-none text-body"><span class="fs-4 fw-semibold d-block">{{ $counts['expired_batches'] }}</span><small>Lotes vencidos</small></a></div>
    </div>
</div>
