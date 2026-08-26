<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWarehouseDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_warehouse_can_be_created(): void
    {
        $warehouse = Warehouse::create(['name' => 'Almacén Central']);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Almacén Central',
        ]);
    }

    public function test_warehouse_name_must_be_unique_at_database_level(): void
    {
        Warehouse::factory()->create(['name' => 'Almacén Central']);

        $this->expectException(QueryException::class);

        Warehouse::factory()->create(['name' => 'Almacén Central']);
    }

    public function test_a_user_can_belong_to_a_warehouse(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $user->warehouses()->attach($warehouse);

        $this->assertTrue($user->warehouses->contains($warehouse));
    }

    public function test_a_user_can_belong_to_multiple_warehouses(): void
    {
        $user = User::factory()->create();
        $warehouses = Warehouse::factory()->count(2)->create();

        $user->warehouses()->attach($warehouses);

        $this->assertCount(2, $user->warehouses);
    }

    public function test_a_warehouse_can_have_multiple_users(): void
    {
        $warehouse = Warehouse::factory()->create();
        $users = User::factory()->count(2)->create();

        $warehouse->users()->attach($users);

        $this->assertCount(2, $warehouse->users);
    }

    public function test_a_user_warehouse_relationship_cannot_be_duplicated(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $user->warehouses()->attach($warehouse);

        $this->expectException(QueryException::class);

        $user->warehouses()->attach($warehouse);
    }

    public function test_an_administrator_is_recognized_as_an_admin(): void
    {
        $user = User::factory()->administrator()->create();

        $this->assertTrue($user->isAdmin());
    }

    public function test_root_is_recognized_as_an_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::ROOT]);

        $this->assertTrue($user->isAdmin());
    }

    public function test_a_warehouse_manager_is_not_recognized_as_an_admin(): void
    {
        $user = User::factory()->warehouseManager()->create();

        $this->assertFalse($user->isAdmin());
    }

    public function test_a_nurse_is_not_recognized_as_an_admin(): void
    {
        $user = User::factory()->nurse()->create();

        $this->assertFalse($user->isAdmin());
    }

    public function test_user_factory_creates_a_valid_user(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->last_name_one);
        $this->assertSame(UserRole::WAREHOUSE_MANAGER, $user->role);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_legacy_user_role_can_still_be_read_safely(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->assertSame(UserRole::LEGACY_USER, $user->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_only_normal_business_roles_are_selectable(): void
    {
        $selectableRoles = array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => $role->selectable(),
        ));

        $this->assertSame([
            UserRole::ADMINISTRATOR,
            UserRole::WAREHOUSE_MANAGER,
            UserRole::NURSE,
        ], $selectableRoles);
    }
}
