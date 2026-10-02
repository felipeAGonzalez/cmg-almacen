<?php

namespace App\Services;

use App\Enums\NursingVoucherReturnStatus;
use App\Enums\NursingVoucherStatus;
use App\Models\InventoryBatch;
use App\Models\NursingVoucher;
use App\Models\NursingVoucherAllocation;
use App\Models\NursingVoucherItem;
use App\Models\NursingVoucherReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NursingVoucherReturnService
{
    /** @return array<int, string> */
    public function returnableQuantities(NursingVoucher $voucher): array
    {
        $reserved = DB::table('nursing_voucher_return_items')
            ->join('nursing_voucher_returns', 'nursing_voucher_returns.id', '=', 'nursing_voucher_return_items.nursing_voucher_return_id')
            ->join('nursing_voucher_allocations', 'nursing_voucher_allocations.id', '=', 'nursing_voucher_return_items.nursing_voucher_allocation_id')
            ->join('nursing_voucher_fulfillment_items', 'nursing_voucher_fulfillment_items.id', '=', 'nursing_voucher_allocations.nursing_voucher_fulfillment_item_id')
            ->where('nursing_voucher_returns.nursing_voucher_id', $voucher->id)
            ->whereIn('nursing_voucher_returns.status', $this->activeStatuses())
            ->groupBy('nursing_voucher_fulfillment_items.nursing_voucher_item_id')
            ->selectRaw('nursing_voucher_fulfillment_items.nursing_voucher_item_id, SUM(nursing_voucher_return_items.quantity) as quantity')
            ->pluck('quantity', 'nursing_voucher_item_id');

        return $voucher->items->mapWithKeys(fn (NursingVoucherItem $item): array => [
            $item->id => bcsub($item->supplied_quantity, (string) ($reserved[$item->id] ?? '0'), 3),
        ])->all();
    }

    public function request(NursingVoucher $voucher, User $requester, array $items, ?string $notes): NursingVoucherReturn
    {
        return DB::transaction(function () use ($voucher, $requester, $items, $notes): NursingVoucherReturn {
            $lockedVoucher = NursingVoucher::query()->lockForUpdate()->findOrFail($voucher->id);
            if (! in_array($lockedVoucher->status, [NursingVoucherStatus::PARTIALLY_SUPPLIED, NursingVoucherStatus::SUPPLIED], true)) {
                throw ValidationException::withMessages([
                    'items' => 'Sólo es posible devolver productos que ya fueron surtidos.',
                ]);
            }

            $quantities = collect($items)
                ->filter(fn (array $item): bool => bccomp((string) ($item['quantity'] ?? '0'), '0', 3) > 0)
                ->keyBy(fn (array $item): int => (int) $item['voucher_item_id']);

            if ($quantities->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Selecciona al menos un producto y una cantidad para devolver.',
                ]);
            }

            $voucherItems = NursingVoucherItem::query()
                ->where('nursing_voucher_id', $lockedVoucher->id)
                ->whereIn('id', $quantities->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($voucherItems->count() !== $quantities->count()) {
                throw ValidationException::withMessages(['items' => 'Uno de los productos no pertenece al vale.']);
            }

            $return = $lockedVoucher->returns()->create([
                'requested_by' => $requester->id,
                'status' => NursingVoucherReturnStatus::PENDING,
                'requested_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($quantities as $voucherItemId => $input) {
                $this->allocateReturn(
                    $return,
                    $voucherItems->get($voucherItemId),
                    (string) $input['quantity'],
                );
            }

            return $return->load('items.allocation.inventoryBatch');
        });
    }

    public function receive(NursingVoucherReturn $return, User $receiver): NursingVoucherReturn
    {
        return DB::transaction(function () use ($return, $receiver): NursingVoucherReturn {
            $lockedReturn = NursingVoucherReturn::query()->lockForUpdate()->findOrFail($return->id);
            $this->ensurePending($lockedReturn);

            $items = $lockedReturn->items()->with('allocation')->lockForUpdate()->get();
            foreach ($items as $item) {
                $batch = InventoryBatch::query()->lockForUpdate()->findOrFail($item->allocation->inventory_batch_id);
                $batch->update([
                    'available_quantity' => bcadd($batch->available_quantity, $item->quantity, 3),
                ]);
            }

            $lockedReturn->update([
                'status' => NursingVoucherReturnStatus::RECEIVED,
                'received_by' => $receiver->id,
                'received_at' => now(),
            ]);

            return $lockedReturn->fresh();
        });
    }

    public function reject(NursingVoucherReturn $return, User $actor, string $reason): void
    {
        DB::transaction(function () use ($return, $actor, $reason): void {
            $lockedReturn = NursingVoucherReturn::query()->lockForUpdate()->findOrFail($return->id);
            $this->ensurePending($lockedReturn);
            $lockedReturn->update([
                'status' => NursingVoucherReturnStatus::REJECTED,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
        });
    }

    public function cancel(NursingVoucherReturn $return, User $actor): void
    {
        DB::transaction(function () use ($return, $actor): void {
            $lockedReturn = NursingVoucherReturn::query()->lockForUpdate()->findOrFail($return->id);
            $this->ensurePending($lockedReturn);
            $lockedReturn->update([
                'status' => NursingVoucherReturnStatus::CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);
        });
    }

    private function allocateReturn(
        NursingVoucherReturn $return,
        NursingVoucherItem $voucherItem,
        string $requestedQuantity,
    ): void {
        $allocations = NursingVoucherAllocation::query()
            ->whereHas('fulfillmentItem', fn ($query) => $query->where('nursing_voucher_item_id', $voucherItem->id))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $requestedQuantity;
        foreach ($allocations as $allocation) {
            $alreadyReturned = $allocation->returnItems()
                ->whereHas('nursingReturn', fn ($query) => $query->whereIn('status', $this->activeStatuses()))
                ->sum('quantity');
            $available = bcsub($allocation->quantity, (string) $alreadyReturned, 3);
            if (bccomp($available, '0', 3) <= 0) {
                continue;
            }

            $quantity = bccomp($remaining, $available, 3) <= 0 ? $remaining : $available;
            $return->items()->create([
                'nursing_voucher_allocation_id' => $allocation->id,
                'quantity' => $quantity,
            ]);
            $remaining = bcsub($remaining, $quantity, 3);
            if (bccomp($remaining, '0', 3) === 0) {
                break;
            }
        }

        if (bccomp($remaining, '0', 3) > 0) {
            throw ValidationException::withMessages([
                'items' => 'La cantidad indicada supera lo disponible para devolución.',
            ]);
        }
    }

    private function ensurePending(NursingVoucherReturn $return): void
    {
        if ($return->status !== NursingVoucherReturnStatus::PENDING) {
            throw ValidationException::withMessages([
                'return' => 'Esta devolución ya no se encuentra pendiente.',
            ]);
        }
    }

    /** @return list<string> */
    private function activeStatuses(): array
    {
        return [
            NursingVoucherReturnStatus::PENDING->value,
            NursingVoucherReturnStatus::RECEIVED->value,
        ];
    }
}
