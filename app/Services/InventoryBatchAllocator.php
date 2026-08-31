<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InventoryBatchAllocator
{
    /** @return Collection<int, array{batch: InventoryBatch, quantity: string}> */
    public function allocate(
        InventoryItem $sourceInventoryItem,
        string $requestedQuantity,
        string $errorKey = 'items',
        string $insufficientStockMessage = 'No hay existencia utilizable suficiente para transferir este producto.',
    ): Collection {
        $batches = $sourceInventoryItem->batches()
            ->where('available_quantity', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', today()))
            ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiration_date')
            ->orderByRaw('CASE WHEN expiration_date IS NULL THEN created_at ELSE NULL END')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $requestedQuantity;
        $allocations = collect();

        foreach ($batches as $batch) {
            if (bccomp($remaining, '0', 3) <= 0) {
                break;
            }

            $quantity = bccomp($batch->available_quantity, $remaining, 3) <= 0
                ? $batch->available_quantity
                : $remaining;

            $allocations->push(['batch' => $batch, 'quantity' => $quantity]);
            $remaining = bcsub($remaining, $quantity, 3);
        }

        if (bccomp($remaining, '0', 3) > 0) {
            throw ValidationException::withMessages([
                $errorKey => $insufficientStockMessage,
            ]);
        }

        return $allocations;
    }
}
