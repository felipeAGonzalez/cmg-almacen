<?php

namespace Tests\Feature;

use App\Enums\AdministrationVoucherStatus;
use App\Enums\UserRole;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AdministrationVoucherFulfillmentService;
use App\Services\AdministrationVoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdministrationVoucherManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_root_create_without_stock_but_manager_and_nurse_cannot(): void
    {
        [$warehouse, $cabinet, $product] = $this->context();
        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $voucher = app(AdministrationVoucherService::class)->create($user, $warehouse, $cabinet, [['product_id' => $product->id, 'quantity' => '10.500']]);
            $this->assertSame(AdministrationVoucherStatus::PENDING, $voucher->status);
            $this->assertSame('10.500', $voucher->items->first()->pendingQuantity());
        }
        $this->assertFalse(User::factory()->warehouseManager()->create()->can('create', AdministrationVoucher::class));
        $this->assertFalse(User::factory()->nurse()->create()->can('create', AdministrationVoucher::class));
    }

    public function test_context_products_duplicates_and_quantities_are_validated(): void
    {
        [$warehouse, $cabinet, $product] = $this->context();
        $admin = User::factory()->administrator()->create();
        $foreignCabinet = Cabinet::factory()->create();
        $this->actingAs($admin)->post(route('administration-vouchers.store'), ['warehouse_id' => $warehouse->id, 'cabinet_id' => $foreignCabinet->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertSessionHasErrors('cabinet_id');
        $this->post(route('administration-vouchers.store'), ['warehouse_id' => $warehouse->id, 'cabinet_id' => $cabinet->id, 'items' => [['product_id' => $product->id, 'quantity' => 1], ['product_id' => $product->id, 'quantity' => 2]]])->assertSessionHasErrors('items.1.product_id');
        $this->post(route('administration-vouchers.store'), ['warehouse_id' => $warehouse->id, 'cabinet_id' => $cabinet->id, 'items' => [['product_id' => $product->id, 'quantity' => 0]]])->assertSessionHasErrors('items.0.quantity');
        $other = Product::factory()->create();
        $this->expectException(ValidationException::class);
        app(AdministrationVoucherService::class)->create($admin, $warehouse, $cabinet, [['product_id' => $other->id, 'quantity' => 1]]);
    }

    public function test_partial_then_complete_fulfillment_creates_traceable_transfers(): void
    {
        [$warehouse, $cabinet, $product, $source, $destination] = $this->context();
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $voucher = app(AdministrationVoucherService::class)->create($admin, $warehouse, $cabinet, [['product_id' => $product->id, 'quantity' => 10]]);
        $batch = $this->batch($source, '10');
        $service = app(AdministrationVoucherFulfillmentService::class);
        $first = $service->fulfill($voucher, $manager, [['voucher_item_id' => $voucher->items->first()->id, 'quantity' => 6]]);
        $this->assertSame($voucher->id, $first->administration_voucher_id);
        $this->assertSame(AdministrationVoucherStatus::PARTIALLY_SUPPLIED, $voucher->fresh()->status);
        $this->assertSame('4.000', $voucher->items->first()->fresh()->pendingQuantity());
        $this->assertSame('4.000', $batch->fresh()->available_quantity);
        $derived = $destination->batches()->firstOrFail();
        $this->assertSame($batch->id, $derived->source_batch_id);
        $this->assertSame('6.000', $derived->available_quantity);

        $second = $service->fulfill($voucher->fresh(), $manager, [['voucher_item_id' => $voucher->items->first()->id, 'quantity' => 4]]);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(AdministrationVoucherStatus::SUPPLIED, $voucher->fresh()->status);
        $this->assertNotNull($voucher->fresh()->completed_at);
        $this->assertSame('10.000', $derived->fresh()->available_quantity);
    }

    public function test_fefo_multibatch_and_insufficient_stock_are_atomic(): void
    {
        [$warehouse, $cabinet, $product, $source] = $this->context();
        $admin = User::factory()->administrator()->create();
        $voucher = app(AdministrationVoucherService::class)->create($admin, $warehouse, $cabinet, [['product_id' => $product->id, 'quantity' => 8]]);
        $expired = $this->batch($source, '20', '2026-01-01');
        $first = $this->batch($source, '3', '2027-01-01');
        $second = $this->batch($source, '3', null);
        try {
            app(AdministrationVoucherFulfillmentService::class)->fulfill($voucher, $admin, [['voucher_item_id' => $voucher->items->first()->id, 'quantity' => 8]]);
            $this->fail('Expected insufficient stock.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('inventory_transfers', 0);
        }
        $this->assertSame('3.000', $first->fresh()->available_quantity);
        app(AdministrationVoucherFulfillmentService::class)->fulfill($voucher, $admin, [['voucher_item_id' => $voucher->items->first()->id, 'quantity' => 5]]);
        $this->assertSame('0.000', $first->fresh()->available_quantity);
        $this->assertSame('1.000', $second->fresh()->available_quantity);
        $this->assertSame('20.000', $expired->fresh()->available_quantity);
    }

    public function test_authorization_reject_cancel_and_terminal_rules_are_enforced(): void
    {
        [$warehouse, $cabinet, $product, $source] = $this->context();
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->warehouseManager()->create();
        $manager->warehouses()->attach($warehouse);
        $foreign = User::factory()->warehouseManager()->create();
        $foreign->warehouses()->attach(Warehouse::factory()->create());
        $nurse = User::factory()->nurse()->create();
        $voucher = app(AdministrationVoucherService::class)->create($admin, $warehouse, $cabinet, [['product_id' => $product->id, 'quantity' => 2]]);
        $this->assertTrue($manager->can('fulfill', $voucher));
        $this->assertFalse($foreign->can('fulfill', $voucher));
        $this->assertFalse($nurse->can('view', $voucher));
        $this->assertFalse($manager->can('cancel', $voucher));
        app(AdministrationVoucherService::class)->reject($voucher, $manager, 'Sin disponibilidad');
        $this->assertSame(AdministrationVoucherStatus::REJECTED, $voucher->fresh()->status);
        $this->assertDatabaseCount('inventory_transfers', 0);
        $this->expectException(ValidationException::class);
        app(AdministrationVoucherFulfillmentService::class)->fulfill($voucher->fresh(), $admin, [['voucher_item_id' => $voucher->items->first()->id, 'quantity' => 1]]);
    }

    private function context(): array
    {
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create();
        $source = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        $destination = InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();

        return [$warehouse, $cabinet, $product, $source, $destination];
    }

    private function batch(InventoryItem $item, string $quantity, ?string $expiration = '2027-01-01'): InventoryBatch
    {
        $entry = $item->stockable->entries()->create(['supplier_id' => Supplier::factory()->for($item->stockable)->create()->id, 'invoice_number' => uniqid('AV-'), 'invoice_date' => today()]);
        $entryItem = $entry->items()->create(['inventory_item_id' => $item->id, 'quantity' => $quantity, 'unit_cost' => 1, 'expiration_date' => $expiration]);

        return $entryItem->batch()->create(['inventory_item_id' => $item->id, 'internal_lot' => uniqid('LOT-'), 'expiration_date' => $expiration, 'received_quantity' => $quantity, 'available_quantity' => $quantity, 'unit_cost' => 1]);
    }
}
