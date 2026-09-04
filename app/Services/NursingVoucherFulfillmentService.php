<?php

namespace App\Services;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Models\InventoryItem;
use App\Models\NursingVoucher;
use App\Models\NursingVoucherFulfillment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class NursingVoucherFulfillmentService
{
    public function __construct(
        private readonly InventoryBatchAllocator $allocator,
        private readonly NursingVoucherNotificationService $notifications,
    ) {}

    public function fulfill(
        NursingVoucher $voucher,
        User $user,
        array $items,
        ?string $notes = null,
    ): NursingVoucherFulfillment {
        Gate::forUser($user)->authorize('fulfill', $voucher);

        return DB::transaction(function () use ($voucher, $user, $items, $notes): NursingVoucherFulfillment {
            $lockedVoucher = NursingVoucher::query()->lockForUpdate()->findOrFail($voucher->getKey());

            if (! in_array($lockedVoucher->status, [
                NursingVoucherStatus::PENDING,
                NursingVoucherStatus::PARTIALLY_SUPPLIED,
            ], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Este vale ya no puede surtirse.',
                ]);
            }

            $requestedIds = collect($items)->pluck('voucher_item_id')->map(fn ($id): int => (int) $id);
            $voucherItems = $lockedVoucher->items()
                ->whereIn('id', $requestedIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($voucherItems->count() !== $requestedIds->unique()->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los productos no pertenece al vale.',
                ]);
            }

            $prepared = $this->prepareAllocations($lockedVoucher, $voucherItems, $items);
            $fulfillment = $lockedVoucher->fulfillments()->create([
                'supplied_by' => $user->getKey(),
                'supplied_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($prepared as $entry) {
                $voucherItem = $entry['voucher_item'];
                $quantity = $entry['quantity'];
                $fulfillmentItem = $fulfillment->items()->create([
                    'nursing_voucher_item_id' => $voucherItem->getKey(),
                    'quantity' => $quantity,
                ]);

                foreach ($entry['allocations'] as $allocation) {
                    $batch = $allocation['batch'];
                    $allocatedQuantity = $allocation['quantity'];
                    $batch->update([
                        'available_quantity' => bcsub($batch->available_quantity, $allocatedQuantity, 3),
                    ]);
                    $fulfillmentItem->allocations()->create([
                        'inventory_batch_id' => $batch->getKey(),
                        'quantity' => $allocatedQuantity,
                    ]);
                }

                $voucherItem->update([
                    'supplied_quantity' => bcadd($voucherItem->supplied_quantity, $quantity, 3),
                ]);
            }

            $this->refreshStatus($lockedVoucher);
            $this->notifications->notifyAfterFulfillment($lockedVoucher->refresh());

            return $fulfillment->load('items.allocations');
        });
    }

    private function prepareAllocations(
        NursingVoucher $voucher,
        Collection $voucherItems,
        array $items,
    ): Collection {
        return collect($items)->map(function (array $item, int $index) use ($voucher, $voucherItems): array {
            $voucherItem = $voucherItems->get((int) $item['voucher_item_id']);
            $quantity = (string) $item['quantity'];
            $pending = $voucherItem->pendingQuantity();

            if (bccomp($quantity, $pending, 3) > 0) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'La cantidad a surtir no puede superar la cantidad pendiente.',
                ]);
            }

            $inventoryItem = $this->inventoryItemFor($voucher, $voucherItem->product_id);

            return [
                'voucher_item' => $voucherItem,
                'quantity' => $quantity,
                'allocations' => $this->allocator->allocate(
                    $inventoryItem,
                    $quantity,
                    "items.$index.quantity",
                    'No hay existencia utilizable suficiente para surtir este producto.',
                ),
            ];
        });
    }

    private function inventoryItemFor(NursingVoucher $voucher, int $productId): InventoryItem
    {
        $query = $voucher->source_type === NursingSupplySourceType::WAREHOUSE
            ? $voucher->warehouse->inventoryItems()
            : $voucher->sourceCabinet?->inventoryItems();

        $inventoryItem = $query?->where('product_id', $productId)->lockForUpdate()->first();

        if (! $inventoryItem) {
            throw ValidationException::withMessages([
                'items' => 'El producto ya no está configurado en el inventario de origen.',
            ]);
        }

        return $inventoryItem;
    }

    private function refreshStatus(NursingVoucher $voucher): void
    {
        $items = $voucher->items()->lockForUpdate()->get();
        $hasSupplied = $items->contains(fn ($item): bool => bccomp($item->supplied_quantity, '0', 3) > 0);
        $hasPending = $items->contains(fn ($item): bool => bccomp($item->pendingQuantity(), '0', 3) > 0);

        $voucher->update([
            'status' => $hasPending
                ? ($hasSupplied ? NursingVoucherStatus::PARTIALLY_SUPPLIED : NursingVoucherStatus::PENDING)
                : NursingVoucherStatus::SUPPLIED,
            'completed_at' => $hasPending ? null : now(),
        ]);
    }
}
