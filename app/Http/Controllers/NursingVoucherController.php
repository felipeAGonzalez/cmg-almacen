<?php

namespace App\Http\Controllers;

use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Enums\UserRole;
use App\Exceptions\HospitalIntegrationException;
use App\Exceptions\NursingSupplyConfigurationException;
use App\Http\Requests\RejectNursingVoucherRequest;
use App\Http\Requests\StoreNursingVoucherRequest;
use App\Models\NursingVoucher;
use App\Models\Warehouse;
use App\Services\NursingVoucherReturnService;
use App\Services\NursingVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NursingVoucherController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', NursingVoucher::class);
        $user = $request->user();
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(NursingVoucherStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        $query = NursingVoucher::query()->with(['requester', 'warehouse', 'sourceCabinet']);

        if ($user->role === UserRole::NURSE) {
            $query->where('requested_by', $user->getKey());
        } elseif ($user->role === UserRole::WAREHOUSE_MANAGER) {
            $query->where('source_type', NursingSupplySourceType::WAREHOUSE)
                ->whereIn('warehouse_id', $user->warehouses()->select('warehouses.id'));
        }

        $query->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('requested_at', '>=', $date.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('requested_at', '<=', $date.' 23:59:59'));

        if ($user->isAdmin() && isset($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        $vouchers = $query->orderByDesc('requested_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $statuses = NursingVoucherStatus::cases();
        $warehouses = $user->isAdmin()
            ? Warehouse::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('nursing-vouchers.index', compact('vouchers', 'statuses', 'warehouses'));
    }

    public function create(Request $request, NursingVoucherService $service): View
    {
        Gate::authorize('create', NursingVoucher::class);

        try {
            return view('nursing-vouchers.create', $service->creationContextForNurse(
                $request->user(),
                (array) $request->session()->get('hospital_context', []),
            ));
        } catch (ValidationException $exception) {
            return view('nursing-vouchers.create', [
                'creationError' => collect($exception->errors())->flatten()->first(),
            ]);
        } catch (NursingSupplyConfigurationException $exception) {
            return view('nursing-vouchers.create', ['creationError' => $exception->getMessage()]);
        } catch (HospitalIntegrationException $exception) {
            return view('nursing-vouchers.create', ['creationError' => $exception->safeMessage]);
        }
    }

    public function store(
        StoreNursingVoucherRequest $request,
        NursingVoucherService $service,
    ): RedirectResponse {
        $data = $request->validated();
        $voucher = $service->createForNurse(
            $request->user(),
            $data['items'],
            $data['notes'] ?? null,
            (array) $request->session()->get('hospital_context', []),
        );

        return redirect()->route('nursing-vouchers.show', $voucher)
            ->with('success', 'El vale de Enfermería se creó correctamente.');
    }

    public function show(NursingVoucher $nursingVoucher, NursingVoucherReturnService $returnService): View
    {
        Gate::authorize('view', $nursingVoucher);
        $nursingVoucher->load([
            'requester',
            'warehouse',
            'sourceCabinet',
            'items.product.unit',
            'fulfillments.suppliedBy',
            'fulfillments.items.voucherItem.product',
            'fulfillments.items.allocations.inventoryBatch',
            'cancelledBy',
            'rejectedBy',
            'returns.requester',
            'returns.receivedBy',
            'returns.rejectedBy',
            'returns.cancelledBy',
            'returns.items.allocation.inventoryBatch',
            'returns.items.allocation.fulfillmentItem.voucherItem.product.unit',
        ]);

        $productIds = $nursingVoucher->items->pluck('product_id');
        $inventoryItems = ($nursingVoucher->source_type === NursingSupplySourceType::WAREHOUSE
            ? $nursingVoucher->warehouse->inventoryItems()
            : $nursingVoucher->sourceCabinet?->inventoryItems())
            ?->whereIn('product_id', $productIds)
            ->withStockTotals()
            ->get()
            ->keyBy('product_id') ?? collect();

        return view('nursing-vouchers.show', [
            'voucher' => $nursingVoucher,
            'inventoryItems' => $inventoryItems,
            'returnableQuantities' => $returnService->returnableQuantities($nursingVoucher),
        ]);
    }

    public function reject(
        RejectNursingVoucherRequest $request,
        NursingVoucher $nursingVoucher,
        NursingVoucherService $service,
    ): RedirectResponse {
        $service->reject($nursingVoucher, $request->user(), $request->validated('rejection_reason'));

        return back()->with('success', 'Vale rechazado correctamente.');
    }

    public function cancel(
        Request $request,
        NursingVoucher $nursingVoucher,
        NursingVoucherService $service,
    ): RedirectResponse {
        Gate::authorize('cancel', $nursingVoucher);
        $service->cancel($nursingVoucher, $request->user());

        return back()->with('success', 'Vale cancelado correctamente.');
    }
}
