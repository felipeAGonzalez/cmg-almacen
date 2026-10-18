<?php

namespace App\Http\Controllers;

use App\Enums\InventoryOutboundReason;
use App\Models\AdministrationVoucher;
use App\Models\InventoryOutbound;
use App\Models\NursingVoucher;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class VoucherPrintController extends Controller
{
    public function nursing(NursingVoucher $nursingVoucher): View
    {
        Gate::authorize('view', $nursingVoucher);

        $nursingVoucher->load([
            'requester',
            'warehouse',
            'sourceCabinet',
            'items.product.unit',
            'fulfillments.suppliedBy',
            'fulfillments.items.voucherItem.product.unit',
            'fulfillments.items.allocations.inventoryBatch',
        ]);

        abort_unless($nursingVoucher->fulfillments->isNotEmpty(), 404);

        return view('prints.nursing-voucher', [
            'voucher' => $nursingVoucher,
            'detailRows' => $nursingVoucher->fulfillments
                ->flatMap(fn ($fulfillment) => $fulfillment->items->flatMap(
                    fn ($item) => $item->allocations->map(fn ($allocation) => [
                        'fulfillment' => $fulfillment,
                        'item' => $item,
                        'allocation' => $allocation,
                    ]),
                )),
        ]);
    }

    public function administration(AdministrationVoucher $administrationVoucher): View
    {
        Gate::authorize('view', $administrationVoucher);

        $administrationVoucher->load([
            'requester',
            'warehouse',
            'cabinet',
            'items.product.unit',
            'transfers.transferredBy',
            'transfers.items.sourceInventoryItem.product.unit',
            'transfers.items.allocations.sourceBatch',
            'transfers.items.allocations.destinationBatch',
        ]);

        abort_unless($administrationVoucher->transfers->isNotEmpty(), 404);

        return view('prints.administration-voucher', [
            'voucher' => $administrationVoucher,
            'detailRows' => $administrationVoucher->transfers
                ->flatMap(fn ($transfer) => $transfer->items->flatMap(
                    fn ($item) => $item->allocations->map(fn ($allocation) => [
                        'transfer' => $transfer,
                        'item' => $item,
                        'allocation' => $allocation,
                    ]),
                )),
        ]);
    }

    public function manual(Warehouse $warehouse, InventoryOutbound $inventoryOutbound): View
    {
        abort_unless($inventoryOutbound->warehouse_id === $warehouse->id, 404);
        abort_unless($inventoryOutbound->reason === InventoryOutboundReason::PATIENT_CARE, 404);

        $inventoryOutbound->load([
            'processedBy',
            'sourceCabinet:id,name,warehouse_id',
            'items.inventoryItem.product.unit',
            'items.allocations.inventoryBatch',
        ]);

        return view($inventoryOutbound->isPamedicVoucher()
            ? 'prints.manual-voucher-pamedic'
            : 'prints.manual-voucher', [
                'warehouse' => $warehouse,
                'outbound' => $inventoryOutbound,
                'detailRows' => $inventoryOutbound->items->flatMap(
                    fn ($item) => $item->allocations->map(fn ($allocation) => [
                        'item' => $item,
                        'allocation' => $allocation,
                    ]),
                ),
            ]);
    }
}
