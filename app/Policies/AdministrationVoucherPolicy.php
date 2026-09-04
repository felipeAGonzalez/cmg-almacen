<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AdministrationVoucher;
use App\Models\User;

class AdministrationVoucherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->role === UserRole::WAREHOUSE_MANAGER;
    }

    public function view(User $user, AdministrationVoucher $voucher): bool
    {
        return $user->isAdmin() || ($user->role === UserRole::WAREHOUSE_MANAGER && $user->warehouses()->whereKey($voucher->warehouse_id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function fulfill(User $user, AdministrationVoucher $voucher): bool
    {
        return $this->view($user, $voucher);
    }

    public function reject(User $user, AdministrationVoucher $voucher): bool
    {
        return $this->view($user, $voucher);
    }

    public function cancel(User $user, AdministrationVoucher $voucher): bool
    {
        return $user->isAdmin();
    }
}
