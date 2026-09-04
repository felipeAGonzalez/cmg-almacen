<?php

namespace App\Services;

use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function __construct(private readonly InventoryBatchAllocator $allocator) {}

    public function transfer(
        Warehouse $warehouse,
        Cabinet $cabinet,
        User $user,
        array $items,
        ?string $notes = null,
        ?AdministrationVoucher $administrationVoucher = null,
    ): InventoryTransfer {
        return DB::transaction(function () use ($warehouse, $cabinet, $user, $items, $notes, $administrationVoucher): InventoryTransfer {
            $lockedCabinet = $warehouse->cabinets()->lockForUpdate()->findOrFail($cabinet->getKey());
            $sourceItems = $warehouse->inventoryItems()
                ->whereIn('id', collect($items)->pluck('source_inventory_item_id'))
                ->lockForUpdate()->get()->keyBy('id');

            $resolved = collect($items)->map(function (array $item, int $index) use ($lockedCabinet, $sourceItems): array {
                $source = $sourceItems->get((int) $item['source_inventory_item_id']);
                if (! $source) {
                    throw ValidationException::withMessages(["items.$index.source_inventory_item_id" => 'El producto no pertenece al inventario principal de este almacén.']);
                }
                $destination = $lockedCabinet->inventoryItems()->where('product_id', $source->product_id)->lockForUpdate()->first();
                if (! $destination) {
                    throw ValidationException::withMessages(["items.$index.source_inventory_item_id" => 'El producto no está configurado en el inventario del gabinete.']);
                }

                return [...$item, 'source' => $source, 'destination' => $destination];
            });

            $transfer = $warehouse->inventoryTransfers()->create([
                'administration_voucher_id' => $administrationVoucher?->getKey(),
                'cabinet_id' => $lockedCabinet->getKey(),
                'transferred_by' => $user->getKey(),
                'transferred_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($resolved as $index => $item) {
                $transferItem = $transfer->items()->create([
                    'source_inventory_item_id' => $item['source']->getKey(),
                    'destination_inventory_item_id' => $item['destination']->getKey(),
                    'requested_quantity' => $item['quantity'],
                ]);
                $allocations = $this->allocator->allocate($item['source'], (string) $item['quantity'], "items.$index.quantity");
                foreach ($allocations as $allocation) {
                    $sourceBatch = $allocation['batch'];
                    $quantity = $allocation['quantity'];
                    $sourceBatch->update(['available_quantity' => bcsub($sourceBatch->available_quantity, $quantity, 3)]);
                    $destinationBatch = $this->destinationBatch($item['destination'], $sourceBatch, $quantity);
                    $transferItem->allocations()->create([
                        'source_batch_id' => $sourceBatch->getKey(),
                        'destination_batch_id' => $destinationBatch->getKey(),
                        'quantity' => $quantity,
                    ]);
                }
            }

            return $transfer;
        });
    }

    private function destinationBatch(InventoryItem $destination, InventoryBatch $source, string $quantity): InventoryBatch
    {
        $batch = $destination->batches()->where('source_batch_id', $source->getKey())->lockForUpdate()->first();
        if ($batch) {
            $batch->update([
                'received_quantity' => bcadd($batch->received_quantity, $quantity, 3),
                'available_quantity' => bcadd($batch->available_quantity, $quantity, 3),
            ]);

            return $batch;
        }

        $batch = $destination->batches()->create([
            'entry_item_id' => null,
            'source_batch_id' => $source->getKey(),
            'internal_lot' => 'PENDING-'.Str::uuid(),
            'manufacturer_lot' => $source->manufacturer_lot,
            'expiration_date' => $source->expiration_date,
            'received_quantity' => $quantity,
            'available_quantity' => $quantity,
            'unit_cost' => $source->unit_cost,
        ]);
        $batch->update(['internal_lot' => sprintf('INT-%s-%06d', $batch->created_at->format('Ymd'), $batch->getKey())]);

        return $batch;
    }
}
