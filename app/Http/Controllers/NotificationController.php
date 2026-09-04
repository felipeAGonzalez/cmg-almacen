<?php

namespace App\Http\Controllers;

use App\Enums\NursingSupplySourceType;
use App\Enums\UserRole;
use App\Models\AdministrationVoucher;
use App\Models\NursingVoucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $pending = $user->unreadNotifications()->latest()->paginate(25, ['*'], 'pending_page');
        $previous = $user->readNotifications()->latest()->paginate(25, ['*'], 'previous_page');
        $notifications = $pending->getCollection()->merge($previous->getCollection());
        $nursingIds = $notifications->pluck('data.nursing_voucher_id')->filter()->unique();
        $administrationIds = $notifications->pluck('data.administration_voucher_id')->filter()->unique();

        $nursing = NursingVoucher::query()->whereKey($nursingIds)
            ->when($user->role === UserRole::NURSE, fn ($query) => $query->where(function ($query) use ($user): void {
                $query->where('requested_by', $user->getKey())->orWhere(fn ($query) => $query->where('source_type', NursingSupplySourceType::CABINET)->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id')));
            }))
            ->when($user->role === UserRole::WAREHOUSE_MANAGER, fn ($query) => $query->where('source_type', NursingSupplySourceType::WAREHOUSE)->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id')))
            ->pluck('id')->flip();
        $administration = AdministrationVoucher::query()->whereKey($administrationIds)
            ->when($user->role === UserRole::WAREHOUSE_MANAGER, fn ($query) => $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id')))
            ->pluck('id')->flip();

        $actionLinks = $notifications->mapWithKeys(function ($notification) use ($nursing, $administration): array {
            $nursingId = $notification->data['nursing_voucher_id'] ?? null;
            $administrationId = $notification->data['administration_voucher_id'] ?? null;
            if ($nursingId && $nursing->has($nursingId)) {
                return [$notification->id => route('nursing-vouchers.show', $nursingId)];
            }
            if ($administrationId && $administration->has($administrationId)) {
                return [$notification->id => route('administration-vouchers.show', $administrationId)];
            }

            return [];
        });

        return view('notifications.index', compact('pending', 'previous', 'actionLinks'));
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_type === $request->user()->getMorphClass() && (string) $notification->notifiable_id === (string) $request->user()->getKey(), 403);
        $notification->markAsRead();

        return back()->with('success', 'La notificación se marcó como leída.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Todas las notificaciones se marcaron como leídas.');
    }
}
