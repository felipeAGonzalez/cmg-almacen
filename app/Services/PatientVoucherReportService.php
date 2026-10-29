<?php

namespace App\Services;

use App\Enums\InventoryOutboundReason;
use App\Enums\NursingVoucherReturnStatus;
use App\Models\InventoryOutbound;
use App\Models\NursingVoucher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PatientVoucherReportService
{
    public function patients(array $filters): LengthAwarePaginator
    {
        $movements = DB::table('nursing_vouchers')
            ->whereBetween('requested_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
            ->select([
                DB::raw('LOWER(TRIM(patient_name)) as patient_key'),
                'patient_name',
                'room_number',
                'requested_at as occurred_at',
                DB::raw("'nursing' as voucher_type"),
            ])
            ->unionAll(
                DB::table('inventory_outbounds')
                    ->where('source_type', InventoryOutbound::SOURCE_MANUAL)
                    ->where('reason', InventoryOutboundReason::PATIENT_CARE->value)
                    ->whereNotNull('patient_name')
                    ->whereBetween('processed_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
                    ->select([
                        DB::raw('LOWER(TRIM(patient_name)) as patient_key'),
                        'patient_name',
                        'room_number',
                        'processed_at as occurred_at',
                        DB::raw("'manual' as voucher_type"),
                    ]),
            );

        $paginator = DB::query()
            ->fromSub($movements, 'patient_vouchers')
            ->when($filters['patient_name'], fn ($query, string $patientName) => $query->where('patient_name', 'like', '%'.$patientName.'%'))
            ->when($filters['room_number'], fn ($query, string $roomNumber) => $query->where('room_number', 'like', '%'.$roomNumber.'%'))
            ->select('patient_key')
            ->selectRaw('MAX(patient_name) as patient_name')
            ->selectRaw('COUNT(*) as vouchers_count')
            ->selectRaw("SUM(CASE WHEN voucher_type = 'nursing' THEN 1 ELSE 0 END) as nursing_count")
            ->selectRaw("SUM(CASE WHEN voucher_type = 'manual' THEN 1 ELSE 0 END) as manual_count")
            ->selectRaw('MAX(occurred_at) as last_voucher_at')
            ->groupBy('patient_key')
            ->orderByDesc('last_voucher_at')
            ->orderBy('patient_name')
            ->paginate(25)
            ->withQueryString();

        $paginator->setCollection($paginator->getCollection()->map(function (object $patient) use ($filters): object {
            $patient->detail_url = route('reports.patient-vouchers.show', [
                'patient' => Crypt::encryptString($patient->patient_key),
                ...$filters,
            ]);

            return $patient;
        }));

        return $paginator;
    }

    /** @return array<string, mixed> */
    public function patient(string $patientKey, array $filters): array
    {
        $nursing = NursingVoucher::query()
            ->whereRaw('LOWER(TRIM(patient_name)) = ?', [$patientKey])
            ->whereBetween('requested_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
            ->with([
                'warehouse:id,name',
                'sourceCabinet:id,name',
                'items.product.unit',
                'items.fulfillmentItems.allocations.returnItems.nursingReturn',
            ])
            ->orderByDesc('requested_at')
            ->get();

        $manual = InventoryOutbound::query()
            ->where('source_type', InventoryOutbound::SOURCE_MANUAL)
            ->where('reason', InventoryOutboundReason::PATIENT_CARE)
            ->whereRaw('LOWER(TRIM(patient_name)) = ?', [$patientKey])
            ->whereBetween('processed_at', [$filters['date_from'].' 00:00:00', $filters['date_to'].' 23:59:59'])
            ->with([
                'warehouse:id,name',
                'sourceCabinet:id,name',
                'items.inventoryItem.product.unit',
                'items.allocations.returnItems',
            ])
            ->orderByDesc('processed_at')
            ->get();

        abort_if($nursing->isEmpty() && $manual->isEmpty(), 404);

        $vouchers = $nursing->map(fn (NursingVoucher $voucher): array => $this->nursingVoucher($voucher))
            ->concat($manual->map(fn (InventoryOutbound $outbound): array => $this->manualVoucher($outbound)))
            ->sortByDesc('occurred_at')
            ->values();

        return [
            'patientName' => $vouchers->first()['patient_name'],
            'vouchers' => $vouchers,
            'consolidatedProducts' => $this->consolidate($vouchers),
        ];
    }

    /** @return array<string, mixed> */
    private function nursingVoucher(NursingVoucher $voucher): array
    {
        return [
            'type' => 'nursing',
            'type_label' => 'Vale de Enfermería',
            'id' => $voucher->id,
            'patient_name' => $voucher->patient_name,
            'room_number' => $voucher->room_number,
            'occurred_at' => $voucher->requested_at,
            'warehouse' => $voucher->warehouse->name,
            'source' => $voucher->sourceCabinet ? 'Gabinete · '.$voucher->sourceCabinet->name : 'Almacén',
            'status_label' => $voucher->status->label(),
            'status_class' => $voucher->status->badgeClass(),
            'url' => route('nursing-vouchers.show', $voucher),
            'products' => $voucher->items->map(function ($item): array {
                $returned = $item->fulfillmentItems
                    ->flatMap->allocations
                    ->flatMap->returnItems
                    ->filter(fn ($returnItem): bool => $returnItem->nursingReturn?->status === NursingVoucherReturnStatus::RECEIVED)
                    ->reduce(fn (string $sum, $returnItem): string => bcadd($sum, $returnItem->quantity, 3), '0.000');

                return $this->productRow(
                    $item->product_id,
                    $item->product->name,
                    $item->product->unit->name,
                    $item->requested_quantity,
                    $item->supplied_quantity,
                    $returned,
                );
            }),
        ];
    }

    /** @return array<string, mixed> */
    private function manualVoucher(InventoryOutbound $outbound): array
    {
        return [
            'type' => 'manual',
            'type_label' => 'Vale manual',
            'id' => $outbound->id,
            'patient_name' => $outbound->patient_name,
            'room_number' => $outbound->room_number,
            'occurred_at' => $outbound->processed_at,
            'warehouse' => $outbound->warehouse->name,
            'source' => $outbound->sourceCabinet ? 'Gabinete · '.$outbound->sourceCabinet->name : 'Almacén',
            'status_label' => 'Registrado',
            'status_class' => 'text-bg-secondary',
            'url' => route('warehouses.outbounds.show', [$outbound->warehouse, $outbound]),
            'products' => $outbound->items->map(function ($item): array {
                $returned = $item->allocations
                    ->flatMap->returnItems
                    ->reduce(fn (string $sum, $returnItem): string => bcadd($sum, $returnItem->quantity, 3), '0.000');

                return $this->productRow(
                    $item->inventoryItem->product_id,
                    $item->inventoryItem->product->name,
                    $item->inventoryItem->product->unit->name,
                    $item->requested_quantity,
                    $item->requested_quantity,
                    $returned,
                );
            }),
        ];
    }

    /** @return array<string, mixed> */
    private function productRow(int $productId, string $name, string $unit, string $requested, string $supplied, string $returned): array
    {
        return [
            'product_id' => $productId,
            'name' => $name,
            'unit' => $unit,
            'requested' => $requested,
            'supplied' => $supplied,
            'returned' => $returned,
            'net' => bcsub($supplied, $returned, 3),
        ];
    }

    private function consolidate(Collection $vouchers): Collection
    {
        return $vouchers->flatMap(fn (array $voucher) => $voucher['products'])
            ->groupBy('product_id')
            ->map(function (Collection $products): array {
                $first = $products->first();

                return [
                    'name' => $first['name'],
                    'unit' => $first['unit'],
                    'requested' => $this->sum($products, 'requested'),
                    'supplied' => $this->sum($products, 'supplied'),
                    'returned' => $this->sum($products, 'returned'),
                    'net' => $this->sum($products, 'net'),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    private function sum(Collection $products, string $field): string
    {
        return $products->reduce(fn (string $sum, array $product): string => bcadd($sum, $product[$field], 3), '0.000');
    }
}
