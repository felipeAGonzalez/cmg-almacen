@php
    $currentCabinet = $cabinet ?? null;
    $hasFilters = collect($filters)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty();
@endphp
<section class="admin-card overflow-hidden">
    <div class="admin-card-header"><h3 class="h6 fw-bold mb-1">Movimientos registrados</h3><p class="small text-body-secondary mb-0">{{ $movements->total() }} {{ $movements->total() === 1 ? 'movimiento' : 'movimientos' }}</p></div>
    @if($movements->isEmpty())
        <div class="empty-state"><span class="empty-state-icon"><i class="bi bi-journal-text"></i></span><h3 class="h5">{{ $hasFilters ? 'No hay movimientos registrados para los filtros seleccionados.' : 'Aún no hay movimientos registrados en este inventario.' }}</h3>@if($hasFilters)<a href="{{ $kardexRoute }}" class="btn btn-light border mt-3">Limpiar filtros</a>@endif</div>
    @else
        <div class="table-responsive"><table class="table admin-table align-middle kardex-table"><thead><tr><th>Fecha</th><th>Movimiento</th><th>Producto</th><th>Lote</th><th class="text-end">Entrada</th><th class="text-end">Salida</th><th>Origen / Destino</th><th>Realizó</th><th>Referencia</th></tr></thead><tbody>
            @foreach($movements as $movement)
                @php
                    $badgeClass = match($movement->movement_type) {
                        'entry', 'transfer_in', 'adjustment_in', 'nursing_voucher_warehouse_return', 'nursing_voucher_cabinet_return' => 'text-bg-success',
                        'transfer_out' => 'text-bg-primary',
                        'nursing_voucher_warehouse_out', 'nursing_voucher_cabinet_out' => 'text-bg-danger',
                        'adjustment_out' => 'text-bg-danger',
                        default => 'text-bg-warning',
                    };
                    $context = match($movement->movement_type) {
                        'entry' => 'Proveedor: '.$movement->source_name,
                        'transfer_out' => 'Gabinete: '.$movement->destination_name,
                        'transfer_in' => 'Almacén: '.$movement->source_name,
                        'manual_outbound' => $movement->reason_label ?: 'Salida del inventario',
                        'adjustment_in', 'adjustment_out' => $movement->reason_label ?: 'Ajuste de inventario',
                        'nursing_voucher_warehouse_out' => 'Vale de Enfermería — Almacén · Paciente: '.$movement->patient_name.' · Habitación '.$movement->room_number,
                        'nursing_voucher_cabinet_out' => 'Vale de Enfermería — Gabinete · Paciente: '.$movement->patient_name.' · Habitación '.$movement->room_number,
                        'nursing_voucher_warehouse_return' => 'Devolución de Enfermería — Almacén · Paciente: '.$movement->patient_name.' · Habitación '.$movement->room_number,
                        'nursing_voucher_cabinet_return' => 'Devolución de Enfermería — Gabinete · Paciente: '.$movement->patient_name.' · Habitación '.$movement->room_number,
                    };
                    $referenceUrl = match($movement->movement_type) {
                        'entry' => route('warehouses.entries.show', [$warehouse, $movement->reference_id]),
                        'transfer_in', 'transfer_out' => route('warehouses.transfers.show', [$warehouse, $movement->reference_id]),
                        'manual_outbound' => route('warehouses.outbounds.show', [$warehouse, $movement->reference_id]),
                        'adjustment_in', 'adjustment_out' => $currentCabinet
                            ? route('warehouses.cabinets.inventory.adjustments.show', [$warehouse, $currentCabinet, $movement->inventory_item_id, $movement->reference_id])
                            : route('warehouses.inventory.adjustments.show', [$warehouse, $movement->inventory_item_id, $movement->reference_id]),
                        'nursing_voucher_warehouse_out', 'nursing_voucher_cabinet_out', 'nursing_voucher_warehouse_return', 'nursing_voucher_cabinet_return' => route('nursing-vouchers.show', $movement->reference_id),
                    };
                    $referenceLabel = match($movement->movement_type) {
                        'entry' => 'Ver entrada'.($movement->reference_code ? ' · '.$movement->reference_code : ''),
                        'transfer_in', 'transfer_out' => 'Ver transferencia',
                        'manual_outbound' => 'Ver salida',
                        'adjustment_in', 'adjustment_out' => 'Ver ajuste',
                        'nursing_voucher_warehouse_out', 'nursing_voucher_cabinet_out', 'nursing_voucher_warehouse_return', 'nursing_voucher_cabinet_return' => 'Vale de Enfermería #'.$movement->reference_id,
                    };
                @endphp
                <tr>
                    <td class="text-nowrap">{{ $movement->occurred_at->format('d/m/Y H:i') }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $movement->movement_label }}</span></td>
                    <td><strong class="d-block">{{ $movement->product_name }}</strong><span class="small text-body-secondary">{{ $movement->unit_name }}</span></td>
                    <td><code class="entry-lot">{{ $movement->internal_lot }}</code>@if($movement->source_internal_lot)<span class="small d-block text-body-secondary mt-1">Origen: {{ $movement->source_internal_lot }}</span>@endif<span class="small d-block text-body-secondary mt-1">Fabricante: {{ $movement->manufacturer_lot ?: 'No indicado' }}</span><span class="small d-block text-body-secondary">{{ $movement->expiration_date ? 'Caduca: '.$movement->expiration_date->format('d/m/Y') : 'Sin caducidad' }}</span></td>
                    <td class="text-end fw-semibold text-success">{{ $movement->direction === 'in' ? $movement->formatted_quantity : '—' }}</td>
                    <td class="text-end fw-semibold text-danger">{{ $movement->direction === 'out' ? $movement->formatted_quantity : '—' }}</td>
                    <td>{{ $context }}</td>
                    <td>{{ $movement->actor_name ? trim($movement->actor_name.' '.$movement->actor_last_name) : '—' }}</td>
                    <td>
                        @if($movement->movement_type === 'nursing_voucher_cabinet_out' && ! Auth::user()->isAdmin())
                            <span class="text-body-secondary">{{ $referenceLabel }}</span>
                        @else

                            <a href="{{ $referenceUrl }}" class="btn btn-sm btn-outline-primary text-nowrap">{{ $referenceLabel }}</a>
                        @endif

                    </td>
                </tr>
            @endforeach
        </tbody></table></div>
        @if($movements->hasPages())<div class="border-top px-3 py-3">{{ $movements->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
    @endif
</section>
