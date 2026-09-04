<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class NurseWarehouseRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_nurse_can_have_zero_or_one_warehouse(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->post(route('users.store'), $this->payload([]))->assertSessionHasNoErrors();
        $nurse = User::where('email', 'nurse-rule@example.com')->firstOrFail();
        $this->assertNull($nurse->warehouseForNursing());

        $warehouse = Warehouse::factory()->create();
        $this->put(route('users.update', $nurse), $this->payload([$warehouse->id]))
            ->assertSessionHasNoErrors();

        $this->assertTrue($nurse->fresh()->warehouseForNursing()?->is($warehouse));
    }

    public function test_nurse_with_multiple_warehouses_is_rejected_by_http_validation(): void
    {
        $warehouses = Warehouse::factory()->count(2)->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('users.store'), $this->payload($warehouses->modelKeys()))
            ->assertSessionHasErrors([
                'warehouse_ids' => 'Una enfermera sólo puede estar asignada a un almacén.',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'nurse-rule@example.com']);
    }

    public function test_warehouse_manager_can_keep_multiple_warehouses(): void
    {
        $warehouses = Warehouse::factory()->count(2)->create();
        $payload = $this->payload($warehouses->modelKeys());
        $payload['role'] = UserRole::WAREHOUSE_MANAGER->value;

        $this->actingAs(User::factory()->administrator()->create())
            ->post(route('users.store'), $payload)
            ->assertSessionHasNoErrors();

        $this->assertCount(2, User::where('email', 'nurse-rule@example.com')->firstOrFail()->warehouses);
    }

    public function test_changing_multi_warehouse_user_to_nurse_requires_reducing_selection(): void
    {
        $manager = User::factory()->create(['role' => UserRole::WAREHOUSE_MANAGER]);
        $warehouses = Warehouse::factory()->count(2)->create();
        $manager->warehouses()->attach($warehouses);

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('users.update', $manager), $this->payload($warehouses->modelKeys()))
            ->assertSessionHasErrors('warehouse_ids');

        $this->assertSame(UserRole::WAREHOUSE_MANAGER, $manager->fresh()->role);
        $this->assertCount(2, $manager->fresh()->warehouses);
    }

    public function test_historical_multiple_assignment_is_not_resolved_silently(): void
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE]);
        $nurse->warehouses()->attach(Warehouse::factory()->count(2)->create());

        $this->expectException(LogicException::class);
        $nurse->warehouseForNursing();
    }

    private function payload(array $warehouseIds): array
    {
        return [
            'name' => 'Nurse',
            'last_name_one' => 'Rule',
            'last_name_two' => '',
            'email' => 'nurse-rule@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => UserRole::NURSE->value,
            'warehouse_ids' => $warehouseIds,
            'hospital_user_id' => '',
        ];
    }
}
