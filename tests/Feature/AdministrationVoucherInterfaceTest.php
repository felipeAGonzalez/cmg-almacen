<?php

namespace Tests\Feature;

use App\Enums\AdministrationVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\OperationalSetting;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationVoucherInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_and_create_access_follow_roles(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->warehouseManager()->create();
        $nurse = User::factory()->nurse()->create();
        $this->actingAs($admin)->get(route('home'))->assertOk()->assertSee('Vales de Administración');
        $this->actingAs($manager)->get(route('home'))->assertOk()->assertSee('Vales de Administración');
        $this->actingAs($nurse)->get(route('home'))->assertOk()->assertDontSee('Vales de Administración');
        $this->actingAs($manager)->get(route('administration-vouchers.create'))->assertForbidden();
        $this->actingAs($nurse)->get(route('administration-vouchers.index'))->assertForbidden();
    }

    public function test_create_contains_contextual_cabinets_and_intersection_products(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create();
        $cabinet = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete destino']);
        $shared = Product::factory()->create(['name' => 'Producto compartido', 'commercial_name' => 'Marca Admin']);
        $warehouseOnly = Product::factory()->create(['name' => 'Sólo almacén']);
        InventoryItem::factory()->forWarehouse($warehouse)->for($shared)->create();
        InventoryItem::factory()->forWarehouse($warehouse)->for($warehouseOnly)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($shared)->create();

        $this->actingAs($admin)->get(route('administration-vouchers.create'))->assertOk()
            ->assertSee('Gabinete destino')->assertSee('Producto compartido')->assertSee('Selecciona un almacén')
            ->assertSee('Selecciona un gabinete')->assertSee('Agregar producto')
            ->assertSee('Marca Admin')->assertSee('Buscar por ingrediente activo o nombre comercial')
            ->assertSee('data-product-search-select', false)
            ->assertSee('function syncRows()', false)
            ->assertSee('Presiona Enter en la cantidad para agregar otro producto.')
            ->assertSee('admin-voucher-product-toolbar', false)
            ->assertSee("bindEnterToAddProduct(form, '[data-admin-item]', addButton);", false);
    }

    public function test_weekly_delegate_create_form_only_contains_assigned_warehouses(): void
    {
        $manager = User::factory()->warehouseManager()->create();
        $assigned = Warehouse::factory()->create(['name' => 'Almacén asignado']);
        Warehouse::factory()->create(['name' => 'Almacén ajeno']);
        $manager->warehouses()->attach($assigned);
        OperationalSetting::current()->update([
            'administration_voucher_delegate_user_id' => $manager->id,
            'administration_voucher_delegate_day' => 1,
        ]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 10:00', config('app.timezone')));

        try {
            $this->actingAs($manager)
                ->get(route('administration-vouchers.create'))
                ->assertOk()
                ->assertSee('Almacén asignado')
                ->assertDontSee('Almacén ajeno');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_index_is_scoped_and_show_is_read_only_with_pending_stock(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create();
        $manager->warehouses()->attach($warehouse);
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create(['name' => 'Solución clínica']);
        $sourceItem = InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $voucher = AdministrationVoucher::create(['requested_by' => $admin->id, 'warehouse_id' => $warehouse->id, 'cabinet_id' => $cabinet->id, 'status' => AdministrationVoucherStatus::PENDING, 'requested_at' => now()]);
        $voucher->items()->create(['product_id' => $product->id, 'requested_quantity' => 10, 'supplied_quantity' => 3]);
        InventoryBatch::query()->create([
            'inventory_item_id' => $sourceItem->id,
            'internal_lot' => 'ADMIN-FULL-001',
            'received_quantity' => '7.000',
            'available_quantity' => '7.000',
            'unit_cost' => '1.0000',
        ]);
        $response = $this->actingAs($manager)->get(route('administration-vouchers.show', $voucher));
        $response->assertOk()->assertSee('Solución clínica')->assertSee('Surtir vale')->assertSee('Disponible:')
            ->assertSee('Surtir completo Solución clínica')->assertSee('data-full-quantity="7.000"', false)
            ->assertSee('Historial de reposiciones')->assertDontSee('Editar')->assertDontSee('Eliminar');
        $this->get(route('administration-vouchers.index'))->assertOk()->assertSee('#'.$voucher->id);
    }

    public function test_admin_can_open_prefilled_edit_form_only_for_pending_voucher(): void
    {
        $admin = User::factory()->administrator()->create();
        $warehouse = Warehouse::factory()->create(['name' => 'Almacén editable']);
        $cabinet = Cabinet::factory()->for($warehouse)->create(['name' => 'Gabinete editable']);
        $product = Product::factory()->create(['name' => 'Producto editable']);
        InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $voucher = AdministrationVoucher::create([
            'requested_by' => $admin->id,
            'warehouse_id' => $warehouse->id,
            'cabinet_id' => $cabinet->id,
            'status' => AdministrationVoucherStatus::PENDING,
            'requested_at' => now(),
            'notes' => 'Corregir cantidades',
        ]);
        $voucher->items()->create(['product_id' => $product->id, 'requested_quantity' => '7.500', 'supplied_quantity' => 0]);

        $this->actingAs($admin)->get(route('administration-vouchers.show', $voucher))
            ->assertOk()->assertSee('Modificar pedido');
        $this->get(route('administration-vouchers.edit', $voucher))
            ->assertOk()->assertSee('Modificar vale de Administración #'.$voucher->id)
            ->assertSee('Almacén editable')->assertSee('Gabinete editable')
            ->assertSee('Producto editable')->assertSee('7.500')
            ->assertSee('Corregir cantidades')->assertSee('Guardar cambios');

        $voucher->update(['status' => AdministrationVoucherStatus::CANCELLED]);
        $this->get(route('administration-vouchers.show', $voucher))->assertOk()->assertDontSee('Modificar pedido');
    }
}
