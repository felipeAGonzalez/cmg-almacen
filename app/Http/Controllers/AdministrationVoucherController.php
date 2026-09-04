<?php

namespace App\Http\Controllers;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\UserRole;
use App\Http\Requests\RejectAdministrationVoucherRequest;
use App\Http\Requests\StoreAdministrationVoucherRequest;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\Warehouse;
use App\Services\AdministrationVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdministrationVoucherController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdministrationVoucher::class);
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(AdministrationVoucherStatus::class)],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'cabinet_id' => ['nullable', 'integer', 'exists:cabinets,id'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $user = $request->user();
        $query = AdministrationVoucher::query()->with(['requester', 'warehouse', 'cabinet']);
        if ($user->role === UserRole::WAREHOUSE_MANAGER) {
            $query->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }
        $query->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['warehouse_id'] ?? null, fn ($query, $value) => $query->where('warehouse_id', $value))
            ->when($filters['cabinet_id'] ?? null, fn ($query, $value) => $query->where('cabinet_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->where('requested_at', '>=', $value.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->where('requested_at', '<=', $value.' 23:59:59'));

        return view('administration-vouchers.index', [
            'vouchers' => $query->orderByDesc('requested_at')->orderByDesc('id')->paginate(25)->withQueryString(),
            'statuses' => AdministrationVoucherStatus::cases(),
            'warehouses' => $user->isAdmin() ? Warehouse::query()->orderBy('name')->get() : $user->warehouses()->orderBy('name')->get(),
            'cabinets' => Cabinet::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AdministrationVoucher::class);
        $warehouses = Warehouse::query()->with(['inventoryItems.product.unit', 'cabinets.inventoryItems.product.unit'])->orderBy('name')->get();

        return view('administration-vouchers.create', compact('warehouses'));
    }

    public function store(StoreAdministrationVoucherRequest $request, AdministrationVoucherService $service): RedirectResponse
    {
        Gate::authorize('create', AdministrationVoucher::class);
        $data = $request->validated();
        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $cabinet = Cabinet::findOrFail($data['cabinet_id']);
        $voucher = $service->create($request->user(), $warehouse, $cabinet, $data['items'], $data['notes'] ?? null);

        return redirect()->route('administration-vouchers.show', $voucher)->with('success', 'El vale de Administración se creó correctamente.');
    }

    public function show(AdministrationVoucher $administrationVoucher): View
    {
        Gate::authorize('view', $administrationVoucher);
        $administrationVoucher->load([
            'requester', 'warehouse', 'cabinet', 'items.product.unit', 'cancelledBy', 'rejectedBy',
            'transfers.transferredBy', 'transfers.items.sourceInventoryItem.product.unit', 'transfers.items.allocations.sourceBatch', 'transfers.items.allocations.destinationBatch',
        ]);
        $inventoryItems = $administrationVoucher->warehouse->inventoryItems()->whereIn('product_id', $administrationVoucher->items->pluck('product_id'))->withStockTotals()->get()->keyBy('product_id');

        return view('administration-vouchers.show', ['voucher' => $administrationVoucher, 'inventoryItems' => $inventoryItems]);
    }

    public function reject(RejectAdministrationVoucherRequest $request, AdministrationVoucher $administrationVoucher, AdministrationVoucherService $service): RedirectResponse
    {
        $service->reject($administrationVoucher, $request->user(), $request->validated('rejection_reason'));

        return back()->with('success', 'Vale rechazado correctamente.');
    }

    public function cancel(Request $request, AdministrationVoucher $administrationVoucher, AdministrationVoucherService $service): RedirectResponse
    {
        $service->cancel($administrationVoucher, $request->user());

        return back()->with('success', 'Vale cancelado correctamente.');
    }
}
