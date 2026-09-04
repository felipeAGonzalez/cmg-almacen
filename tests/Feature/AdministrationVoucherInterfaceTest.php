<?php

namespace Tests\Feature;

use App\Enums\AdministrationVoucherStatus;
use App\Models\AdministrationVoucher;
use App\Models\Cabinet;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
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
        $shared = Product::factory()->create(['name' => 'Producto compartido']);
        $warehouseOnly = Product::factory()->create(['name' => 'Sólo almacén']);
        InventoryItem::factory()->forWarehouse($warehouse)->for($shared)->create();
        InventoryItem::factory()->forWarehouse($warehouse)->for($warehouseOnly)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($shared)->create();

        $this->actingAs($admin)->get(route('administration-vouchers.create'))->assertOk()
            ->assertSee('Gabinete destino')->assertSee('Producto compartido')->assertSee('Selecciona un almacén')
            ->assertSee('Selecciona un gabinete')->assertSee('Agregar producto');
    }

    public function test_index_is_scoped_and_show_is_read_only_with_pending_stock(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->warehouseManager()->create();
        $warehouse = Warehouse::factory()->create();
        $manager->warehouses()->attach($warehouse);
        $cabinet = Cabinet::factory()->for($warehouse)->create();
        $product = Product::factory()->create(['name' => 'Solución clínica']);
        InventoryItem::factory()->forWarehouse($warehouse)->for($product)->create();
        InventoryItem::factory()->forCabinet($cabinet)->for($product)->create();
        $voucher = AdministrationVoucher::create(['requested_by' => $admin->id, 'warehouse_id' => $warehouse->id, 'cabinet_id' => $cabinet->id, 'status' => AdministrationVoucherStatus::PENDING, 'requested_at' => now()]);
        $voucher->items()->create(['product_id' => $product->id, 'requested_quantity' => 10, 'supplied_quantity' => 3]);
        $response = $this->actingAs($manager)->get(route('administration-vouchers.show', $voucher));
        $response->assertOk()->assertSee('Solución clínica')->assertSee('Surtir vale')->assertSee('Disponible:')->assertSee('Historial de reposiciones')->assertDontSee('Editar')->assertDontSee('Eliminar');
        $this->get(route('administration-vouchers.index'))->assertOk()->assertSee('#'.$voucher->id);
    }
}
