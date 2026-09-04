<?php

namespace App\Policies;

use App\Enums\NursingSupplySourceType;
use App\Enums\UserRole;
use App\Models\NursingVoucher;
use App\Models\User;

class NursingVoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMINISTRATOR,
            UserRole::ROOT,
            UserRole::WAREHOUSE_MANAGER,
            UserRole::NURSE,
        ], true);
    }

    public function view(User $user, NursingVoucher $voucher): bool
    {
        if ($user->isAdmin() || $voucher->requested_by === $user->getKey()) {
            return true;
        }

        return $this->canOperate($user, $voucher);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::NURSE;
    }

    public function fulfill(User $user, NursingVoucher $voucher): bool
    {
        return $user->isAdmin() || $this->canOperate($user, $voucher);
    }

    public function reject(User $user, NursingVoucher $voucher): bool
    {
        return $this->fulfill($user, $voucher);
    }

    public function cancel(User $user, NursingVoucher $voucher): bool
    {
        return $user->isAdmin() || $voucher->requested_by === $user->getKey();
    }

    private function canOperate(User $user, NursingVoucher $voucher): bool
    {
        $isAssigned = $user->warehouses()->whereKey($voucher->warehouse_id)->exists();

        if ($voucher->source_type === NursingSupplySourceType::WAREHOUSE) {
            return $user->role === UserRole::WAREHOUSE_MANAGER && $isAssigned;
        }

        return $user->role === UserRole::NURSE && $isAssigned;
    }
}
