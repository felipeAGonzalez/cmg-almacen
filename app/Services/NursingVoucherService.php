<?php

namespace App\Services;

use App\Data\HospitalizedPatient;
use App\Data\NursingSupplySource;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Models\NursingVoucher;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class NursingVoucherService
{
    public function __construct(
        private readonly HospitalPatientService $patients,
        private readonly NursingSupplySourceService $sources,
        private readonly NursingVoucherNotificationService $notifications,
    ) {}

    /**  array{hospitalization: HospitalizedPatient, source: NursingSupplySource, inventoryItems: Collection} */
    public function creationContextForNurse(User $nurse, array $hospitalContext): array
    {
        Gate::forUser($nurse)->authorize('create', NursingVoucher::class);
        $hospitalization = $this->activeHospitalization($this->validatedHospitalContext($hospitalContext));
        $source = $this->sources->resolve($nurse);
        $inventoryItems = ($source->type === NursingSupplySourceType::WAREHOUSE
            ? $source->warehouse->inventoryItems()
            : $source->cabinet->inventoryItems())
            ->with('product.unit')
            ->orderBy(Product::query()->select('name')->whereColumn('products.id', 'inventory_items.product_id'))
            ->get();

        return compact('hospitalization', 'source', 'inventoryItems');
    }

    public function createForNurse(
        User $nurse,
        array $items,
        ?string $notes,
        array $hospitalContext,
    ): NursingVoucher {
        Gate::forUser($nurse)->authorize('create', NursingVoucher::class);

        $context = $this->validatedHospitalContext($hospitalContext);
        $hospitalization = $this->activeHospitalization($context);
        $source = $this->sources->resolve($nurse);
        $productIds = collect($items)->pluck('product_id')->map(fn ($id): int => (int) $id);

        $inventoryItems = $source->type === NursingSupplySourceType::WAREHOUSE
            ? $source->warehouse->inventoryItems()->whereIn('product_id', $productIds)->get()
            : $source->cabinet->inventoryItems()->whereIn('product_id', $productIds)->get();

        if ($inventoryItems->pluck('product_id')->unique()->count() !== $productIds->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'Uno de los productos no está configurado en el inventario de origen.',
            ]);
        }

        return DB::transaction(function () use ($nurse, $items, $notes, $hospitalization, $source): NursingVoucher {
            $voucher = NursingVoucher::query()->create([
                'requested_by' => $nurse->getKey(),
                'warehouse_id' => $source->warehouse->getKey(),
                'source_type' => $source->type,
                'source_cabinet_id' => $source->cabinet?->getKey(),
                'external_patient_id' => $hospitalization->externalPatientId,
                'external_hospitalization_id' => $hospitalization->externalHospitalizationId,
                'patient_name' => $hospitalization->patientName,
                'external_room_id' => $hospitalization->externalRoomId,
                'room_number' => $hospitalization->roomNumber,
                'status' => NursingVoucherStatus::PENDING,
                'requested_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                $voucher->items()->create([
                    'product_id' => $item['product_id'],
                    'requested_quantity' => (string) $item['quantity'],
                    'supplied_quantity' => '0',
                ]);
            }

            $this->notifications->notifyCreated($voucher);

            return $voucher->load('items');
        });
    }

    public function reject(NursingVoucher $voucher, User $user, string $reason): NursingVoucher
    {
        Gate::forUser($user)->authorize('reject', $voucher);

        return DB::transaction(function () use ($voucher, $user, $reason): NursingVoucher {
            $locked = NursingVoucher::query()->lockForUpdate()->findOrFail($voucher->getKey());

            if ($locked->status !== NursingVoucherStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Sólo se puede rechazar un vale pendiente.',
                ]);
            }

            $locked->update([
                'status' => NursingVoucherStatus::REJECTED,
                'rejected_by' => $user->getKey(),
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            $this->notifications->notifyRejected($locked);

            return $locked;
        });
    }

    public function cancel(NursingVoucher $voucher, User $user): NursingVoucher
    {
        Gate::forUser($user)->authorize('cancel', $voucher);

        return DB::transaction(function () use ($voucher, $user): NursingVoucher {
            $locked = NursingVoucher::query()->lockForUpdate()->findOrFail($voucher->getKey());

            if ($locked->status !== NursingVoucherStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Sólo se puede cancelar un vale pendiente.',
                ]);
            }

            $locked->update([
                'status' => NursingVoucherStatus::CANCELLED,
                'cancelled_by' => $user->getKey(),
                'cancelled_at' => now(),
            ]);

            $this->notifications->notifyCancelled($locked, $user);

            return $locked;
        });
    }

    private function validatedHospitalContext(array $context): array
    {
        $required = ['patient_id', 'hospitalization_id', 'room_id', 'room_number'];

        foreach ($required as $key) {
            if (! isset($context[$key]) || ! is_scalar($context[$key]) || trim((string) $context[$key]) === '') {
                throw ValidationException::withMessages([
                    'hospital_context' => 'No existe un contexto válido de hospitalización.',
                ]);
            }

            $context[$key] = trim((string) $context[$key]);
        }

        return $context;
    }

    private function activeHospitalization(array $context): HospitalizedPatient
    {
        $hospitalization = $this->patients->activePatients()->first(
            fn (HospitalizedPatient $patient): bool => $patient->externalHospitalizationId === $context['hospitalization_id'],
        );

        if (! $hospitalization
            || $hospitalization->externalPatientId !== $context['patient_id']
            || $hospitalization->externalRoomId !== $context['room_id']
            || $hospitalization->roomNumber !== $context['room_number']) {
            throw ValidationException::withMessages([
                'hospital_context' => 'La hospitalización ya no se encuentra activa.',
            ]);
        }

        return $hospitalization;
    }
}
