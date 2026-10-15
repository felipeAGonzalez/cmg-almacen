<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\InventoryOutbound;
use App\Models\InventoryOutboundAllocation;
use App\Models\InventoryOutboundReturn;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryOutboundReturnService
{
    public function create(Warehouse $warehouse, InventoryOutbound $outbound, User $receiver, array $items, string $receivedAt, ?string $notes): InventoryOutboundReturn
    {
        return DB::transaction(function () use ($warehouse, $outbound, $receiver, $items, $receivedAt, $notes): InventoryOutboundReturn {
            $lockedOutbound = InventoryOutbound::query()->where('warehouse_id', $warehouse->id)->lockForUpdate()->find($outbound->id);
            if (! $lockedOutbound) {
                throw ValidationException::withMessages(['inventory_outbound_id' => 'La salida seleccionada no pertenece a este almacén.']);
            }

            $quantities = collect($items)->filter(fn (array $item): bool => bccomp((string) ($item['quantity'] ?? '0'), '0', 3) > 0)->keyBy('outbound_item_id');
            if ($quantities->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Captura una cantidad mayor que cero para al menos un producto.']);
            }

            $outboundItems = $lockedOutbound->items()->whereIn('id', $quantities->keys())->lockForUpdate()->get()->keyBy('id');
            if ($outboundItems->count() !== $quantities->count()) {
                throw ValidationException::withMessages(['items' => 'Uno de los productos no pertenece a la salida seleccionada.']);
            }

            $return = InventoryOutboundReturn::create([
                'inventory_outbound_id' => $lockedOutbound->id,
                'warehouse_id' => $warehouse->id,
                'received_by' => $receiver->id,
                'received_at' => $receivedAt,
                'notes' => $notes,
            ]);

            foreach ($quantities as $outboundItemId => $input) {
                $remaining = (string) $input['quantity'];
                $allocations = InventoryOutboundAllocation::query()->where('inventory_outbound_item_id', $outboundItemId)->orderBy('id')->lockForUpdate()->get();

                foreach ($allocations as $allocation) {
                    $returned = (string) $allocation->returnItems()->sum('quantity');
                    $available = bcsub($allocation->quantity, $returned, 3);
                    if (bccomp($available, '0', 3) <= 0) {
                        continue;
                    }

                    $quantity = bccomp($remaining, $available, 3) <= 0 ? $remaining : $available;
                    $return->items()->create(['inventory_outbound_allocation_id' => $allocation->id, 'quantity' => $quantity]);
                    $batch = InventoryBatch::query()->lockForUpdate()->findOrFail($allocation->inventory_batch_id);
                    $batch->update(['available_quantity' => bcadd($batch->available_quantity, $quantity, 3)]);
                    $remaining = bcsub($remaining, $quantity, 3);
                    if (bccomp($remaining, '0', 3) === 0) {
                        break;
                    }
                }

                if (bccomp($remaining, '0', 3) > 0) {
                    throw ValidationException::withMessages(['items' => 'La cantidad indicada supera lo disponible para devolución.']);
                }
            }

            return $return;
        });
    }
}
