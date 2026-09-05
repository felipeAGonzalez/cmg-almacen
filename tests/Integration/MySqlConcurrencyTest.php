<?php

namespace Tests\Integration;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\InventoryOutboundReason;
use App\Enums\NursingSupplySourceType;
use App\Enums\NursingVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\InventoryOutbound;
use App\Models\NursingVoucher;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AdministrationVoucherFulfillmentService;
use App\Services\InventoryBatchAllocator;
use App\Services\InventoryTransferService;
use App\Services\NursingVoucherFulfillmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class MySqlConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql' || DB::getDatabaseName() !== 'cmg_test_mysql') {
            $this->markTestSkipped('Esta prueba requiere la base MySQL exclusiva cmg_test_mysql.');
        }
    }

    public function test_nursing_voucher_concurrent_fulfillments_do_not_over_supply(): void
    {
        [$user, $warehouse, $item, $batch] = $this->warehouseStock('10.000');
        $voucher = NursingVoucher::create([
            'requested_by' => $user->id, 'warehouse_id' => $warehouse->id,
            'source_type' => NursingSupplySourceType::WAREHOUSE,
            'external_patient_id' => 'patient', 'external_hospitalization_id' => (string) Str::uuid(),
            'patient_name' => 'Paciente de integración', 'external_room_id' => 'room', 'room_number' => '101',
            'status' => NursingVoucherStatus::PENDING, 'requested_at' => now(),
        ]);
        $voucherItem = $voucher->items()->create(['product_id' => $item->product_id, 'requested_quantity' => '10.000', 'supplied_quantity' => '0']);

        $codes = $this->race(fn () => app(NursingVoucherFulfillmentService::class)->fulfill(
            NursingVoucher::findOrFail($voucher->id), User::findOrFail($user->id),
            [['voucher_item_id' => $voucherItem->id, 'quantity' => '7.000']],
        ));

        $this->assertSame([0, 2], $codes);
        $this->assertSame('7.000', $voucherItem->fresh()->supplied_quantity);
        $this->assertSame('3.000', $batch->fresh()->available_quantity);
        $this->assertDatabaseCount('nursing_voucher_fulfillments', 1);
    }

    public function test_administration_voucher_concurrent_fulfillments_create_one_transfer(): void
    {
        [$user, $warehouse, $item, $batch] = $this->warehouseStock('10.000');
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($item->product)->create();
        $voucher = AdministrationVoucher::create([
            'requested_by' => $user->id, 'warehouse_id' => $warehouse->id, 'cabinet_id' => $cabinet->id,
            'status' => AdministrationVoucherStatus::PENDING, 'requested_at' => now(),
        ]);
        $voucherItem = $voucher->items()->create(['product_id' => $item->product_id, 'requested_quantity' => '10.000', 'supplied_quantity' => '0']);

        $codes = $this->race(fn () => app(AdministrationVoucherFulfillmentService::class)->fulfill(
            AdministrationVoucher::findOrFail($voucher->id), User::findOrFail($user->id),
            [['voucher_item_id' => $voucherItem->id, 'quantity' => '7.000']],
        ));

        $this->assertSame([0, 2], $codes);
        $this->assertSame('7.000', $voucherItem->fresh()->supplied_quantity);
        $this->assertSame('3.000', $batch->fresh()->available_quantity);
        $this->assertSame(1, $voucher->transfers()->count());
    }

    public function test_concurrent_transfers_do_not_consume_same_batch_twice(): void
    {
        [$user, $warehouse, $item, $batch] = $this->warehouseStock('10.000');
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $destination = InventoryItem::factory()->forCabinet($cabinet)->for($item->product)->create();

        $codes = $this->race(fn () => app(InventoryTransferService::class)->transfer(
            Warehouse::findOrFail($warehouse->id), Cabinet::findOrFail($cabinet->id), User::findOrFail($user->id),
            [['source_inventory_item_id' => $item->id, 'quantity' => '7.000']],
        ));

        $this->assertSame([0, 2], $codes);
        $this->assertSame('3.000', $batch->fresh()->available_quantity);
        $this->assertSame('7.000', $destination->batches()->sum('available_quantity'));
        $this->assertSame(1, $warehouse->inventoryTransfers()->count());
    }

    public function test_concurrent_manual_outbounds_do_not_leave_negative_stock(): void
    {
        [$user, $warehouse, $item, $batch] = $this->warehouseStock('10.000');

        $codes = $this->race(function () use ($user, $warehouse, $item): void {
            DB::transaction(function () use ($user, $warehouse, $item): void {
                $lockedItem = Warehouse::findOrFail($warehouse->id)->inventoryItems()->lockForUpdate()->findOrFail($item->id);
                $outbound = $lockedItem->stockable->inventoryOutbounds()->create([
                    'reason' => InventoryOutboundReason::INTERNAL_CONSUMPTION,
                    'processed_by' => $user->id, 'processed_at' => now(),
                    'source_type' => InventoryOutbound::SOURCE_MANUAL, 'source_id' => null,
                ]);
                $outboundItem = $outbound->items()->create(['inventory_item_id' => $lockedItem->id, 'requested_quantity' => '7.000']);
                $allocations = app(InventoryBatchAllocator::class)->allocate($lockedItem, '7.000');
                foreach ($allocations as $allocation) {
                    $allocation['batch']->update(['available_quantity' => bcsub($allocation['batch']->available_quantity, $allocation['quantity'], 3)]);
                    $outboundItem->allocations()->create(['inventory_batch_id' => $allocation['batch']->id, 'quantity' => $allocation['quantity']]);
                }
            });
        });

        $this->assertSame([0, 2], $codes);
        $this->assertSame('3.000', $batch->fresh()->available_quantity);
        $this->assertSame(1, $warehouse->inventoryOutbounds()->count());
    }

    public function test_mysql_preserves_decimal_precision_and_unique_constraints(): void
    {
        [, $warehouse, $item] = $this->warehouseStock('1.125');
        $tiny = $this->batch($item, '0.001', null);
        $large = $this->batch($item, '999999.999', null);

        $this->assertSame('0.001', $tiny->fresh()->available_quantity);
        $this->assertSame('999999.999', $large->fresh()->available_quantity);
        $this->assertSame('1.1250', $large->fresh()->unit_cost);
        $this->expectException(Throwable::class);
        InventoryItem::factory()->forWarehouse($warehouse)->for($item->product)->create();
    }

    /** @return array{User, Warehouse, InventoryItem, InventoryBatch} */
    private function warehouseStock(string $quantity): array
    {
        $user = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $item = InventoryItem::factory()->forWarehouse($warehouse)->for(Product::factory()->create())->create();

        return [$user, $warehouse, $item, $this->batch($item, $quantity, now()->addMonth()->toDateString())];
    }

    private function batch(InventoryItem $item, string $quantity, ?string $expiration): InventoryBatch
    {
        return InventoryBatch::create([
            'inventory_item_id' => $item->id, 'entry_item_id' => null, 'source_batch_id' => null,
            'internal_lot' => 'MYSQL-'.Str::uuid(), 'manufacturer_lot' => 'FAB-'.Str::random(8),
            'expiration_date' => $expiration, 'received_quantity' => $quantity,
            'available_quantity' => $quantity, 'unit_cost' => '1.1250',
        ]);
    }

    /** @return list<int> */
    private function race(callable $operation): array
    {
        if (! function_exists('pcntl_fork')) {
            throw new RuntimeException('La extensión pcntl es necesaria para esta prueba.');
        }

        $barrier = sys_get_temp_dir().'/cmg-race-'.Str::uuid();
        $children = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::purge();
                while (! file_exists($barrier)) {
                    usleep(1000);
                }
                try {
                    $operation();
                    exit(0);
                } catch (Throwable) {
                    exit(2);
                }
            }
            $children[] = $pid;
        }

        touch($barrier);
        $codes = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $codes[] = pcntl_wexitstatus($status);
        }
        unlink($barrier);
        sort($codes);
        DB::purge();

        return $codes;
    }
}
