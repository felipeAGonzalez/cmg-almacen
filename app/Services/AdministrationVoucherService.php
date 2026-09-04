<?php

namespace App\Services;

use App\Enums\AdministrationVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdministrationVoucherService
{
    public function __construct(private readonly AdministrationVoucherNotificationService $notifications) {}

    public function create(User $requester, Warehouse $warehouse, Cabinet $cabinet, array $items, ?string $notes = null): AdministrationVoucher
    {
        Gate::forUser($requester)->authorize('create', AdministrationVoucher::class);
        if ($cabinet->warehouse_id !== $warehouse->getKey()) {
            throw ValidationException::withMessages(['cabinet_id' => 'El gabinete debe pertenecer al almacén seleccionado.']);
        }
        $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id);
        if ($productIds->count() !== $productIds->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'El producto está repetido en el vale.']);
        }
        foreach ($items as $index => $item) {
            if (! isset($item['quantity']) || ! is_numeric($item['quantity']) || bccomp((string) $item['quantity'], '0', 3) <= 0) {
                throw ValidationException::withMessages(["items.$index.quantity" => 'La cantidad debe ser mayor que cero.']);
            }
        }
        $warehouseProducts = $warehouse->inventoryItems()->whereIn('product_id', $productIds)->pluck('product_id');
        $cabinetProducts = $cabinet->inventoryItems()->whereIn('product_id', $productIds)->pluck('product_id');
        if ($warehouseProducts->unique()->count() !== $productIds->unique()->count() || $cabinetProducts->unique()->count() !== $productIds->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'Uno de los productos no está configurado en ambos inventarios.']);
        }

        return DB::transaction(function () use ($requester, $warehouse, $cabinet, $items, $notes): AdministrationVoucher {
            $voucher = AdministrationVoucher::query()->create([
                'requested_by' => $requester->getKey(), 'warehouse_id' => $warehouse->getKey(), 'cabinet_id' => $cabinet->getKey(),
                'status' => AdministrationVoucherStatus::PENDING, 'requested_at' => now(), 'notes' => $notes,
            ]);
            foreach ($items as $item) {
                $voucher->items()->create(['product_id' => $item['product_id'], 'requested_quantity' => $item['quantity'], 'supplied_quantity' => '0']);
            }
            $this->notifications->notifyCreated($voucher);

            return $voucher->load('items');
        });
    }

    public function reject(AdministrationVoucher $voucher, User $user, string $reason): AdministrationVoucher
    {
        Gate::forUser($user)->authorize('reject', $voucher);

        return DB::transaction(function () use ($voucher, $user, $reason): AdministrationVoucher {
            $locked = AdministrationVoucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            if ($locked->status !== AdministrationVoucherStatus::PENDING) {
                throw ValidationException::withMessages(['status' => 'Sólo se puede rechazar un vale pendiente.']);
            }
            $locked->update(['status' => AdministrationVoucherStatus::REJECTED, 'rejected_by' => $user->getKey(), 'rejected_at' => now(), 'rejection_reason' => trim($reason)]);
            $this->notifications->notifyRejected($locked);

            return $locked;
        });
    }

    public function cancel(AdministrationVoucher $voucher, User $user): AdministrationVoucher
    {
        Gate::forUser($user)->authorize('cancel', $voucher);

        return DB::transaction(function () use ($voucher, $user): AdministrationVoucher {
            $locked = AdministrationVoucher::query()->lockForUpdate()->findOrFail($voucher->getKey());
            if ($locked->status !== AdministrationVoucherStatus::PENDING) {
                throw ValidationException::withMessages(['status' => 'Sólo se puede cancelar un vale pendiente.']);
            }
            $locked->update(['status' => AdministrationVoucherStatus::CANCELLED, 'cancelled_by' => $user->getKey(), 'cancelled_at' => now()]);
            $this->notifications->closeActions($locked);

            return $locked;
        });
    }
}
