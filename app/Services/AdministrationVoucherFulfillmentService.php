<?php

namespace App\Services;

use App\Enums\AdministrationVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\InventoryTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdministrationVoucherFulfillmentService
{
    public function __construct(private readonly InventoryTransferService $transfers, private readonly AdministrationVoucherNotificationService $notifications) {}

    public function fulfill(AdministrationVoucher $voucher, User $user, array $items, ?string $notes = null): InventoryTransfer
    {
        Gate::forUser($user)->authorize('fulfill', $voucher);

        return DB::transaction(function () use ($voucher, $user, $items, $notes): InventoryTransfer {
            $locked = AdministrationVoucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            if (! in_array($locked->status, [AdministrationVoucherStatus::PENDING, AdministrationVoucherStatus::PARTIALLY_SUPPLIED], true)) {
                throw ValidationException::withMessages(['status' => 'Este vale ya no puede surtirse.']);
            }
            $ids = collect($items)->pluck('voucher_item_id')->map(fn ($id) => (int) $id);
            $voucherItems = $locked->items()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($voucherItems->count() !== $ids->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'Uno de los productos no pertenece al vale.']);
            }
            $sourceItems = $locked->warehouse->inventoryItems()->whereIn('product_id', $voucherItems->pluck('product_id'))->lockForUpdate()->get()->keyBy('product_id');
            $transferItems = collect($items)->map(function (array $item, int $index) use ($voucherItems, $sourceItems): array {
                $voucherItem = $voucherItems->get((int) $item['voucher_item_id']);
                $quantity = (string) $item['quantity'];
                if (bccomp($quantity, $voucherItem->pendingQuantity(), 3) > 0) {
                    throw ValidationException::withMessages(["items.$index.quantity" => 'La cantidad a surtir no puede superar la cantidad pendiente.']);
                }
                $source = $sourceItems->get($voucherItem->product_id);
                if (! $source) {
                    throw ValidationException::withMessages(["items.$index.quantity" => 'El producto ya no está configurado en el almacén.']);
                }

                return ['source_inventory_item_id' => $source->getKey(), 'quantity' => $quantity, 'voucher_item' => $voucherItem];
            });

            $transfer = $this->transfers->transfer($locked->warehouse, $locked->cabinet, $user, $transferItems->map(fn ($item) => collect($item)->except('voucher_item')->all())->all(), $notes, $locked);
            foreach ($transferItems as $item) {
                $item['voucher_item']->update(['supplied_quantity' => bcadd($item['voucher_item']->supplied_quantity, $item['quantity'], 3)]);
            }
            $allItems = $locked->items()->lockForUpdate()->get();
            $hasPending = $allItems->contains(fn ($item) => bccomp($item->pendingQuantity(), '0', 3) > 0);
            $locked->update(['status' => $hasPending ? AdministrationVoucherStatus::PARTIALLY_SUPPLIED : AdministrationVoucherStatus::SUPPLIED, 'completed_at' => $hasPending ? null : now()]);
            $this->notifications->notifyAfterFulfillment($locked->refresh());

            return $transfer->load('items.allocations');
        });
    }
}
