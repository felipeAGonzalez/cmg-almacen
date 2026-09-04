<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_access_the_user_index(): void
    {
        $response = $this->actingAs(User::factory()->administrator()->create())->get(route('users.index'));

        $response->assertOk()->assertViewIs('users.index');
    }

    public function test_root_can_access_the_user_index(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => UserRole::ROOT]))->get(route('users.index'));

        $response->assertOk();
    }

    public function test_non_administrative_roles_cannot_access_the_user_index(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_all_user_routes(): void
    {
        $user = User::factory()->create();

        $this->get(route('users.index'))->assertRedirect(route('login'));
        $this->get(route('users.create'))->assertRedirect(route('login'));
        $this->post(route('users.store'))->assertRedirect(route('login'));
        $this->get(route('users.edit', $user))->assertRedirect(route('login'));
        $this->put(route('users.update', $user))->assertRedirect(route('login'));
        $this->delete(route('users.destroy', $user))->assertRedirect(route('login'));
    }

    public function test_root_is_excluded_from_the_normal_user_index(): void
    {
        $administrator = User::factory()->administrator()->create();
        $root = User::factory()->create(['role' => UserRole::ROOT]);
        $managedUser = User::factory()->create();

        $response = $this->actingAs($administrator)->get(route('users.index'));

        $response->assertViewHas('users', function ($users) use ($root, $managedUser): bool {
            return ! $users->contains($root) && $users->contains($managedUser);
        });
    }

    public function test_create_and_edit_forms_receive_selectable_roles_and_ordered_warehouses(): void
    {
        $administrator = User::factory()->administrator()->create();
        Warehouse::factory()->create(['name' => 'Zeta']);
        Warehouse::factory()->create(['name' => 'Almacén Central']);

        foreach ([route('users.create'), route('users.edit', $administrator)] as $route) {
            $response = $this->actingAs($administrator)->get($route);

            $response->assertOk()
                ->assertViewHas('roles', UserRole::selectableCases())
                ->assertViewHas('warehouses', fn ($warehouses): bool => $warehouses->pluck('name')->all() === ['Almacén Central', 'Zeta']);
        }
    }

    public function test_an_administrator_can_create_another_administrator_without_warehouses(): void
    {
        $response = $this->storeUser(UserRole::ADMINISTRATOR);

        $response->assertRedirect(route('users.index'))->assertSessionHas('success', 'Usuario creado correctamente.');
        $this->assertDatabaseHas('users', ['email' => 'new.user@example.com', 'role' => UserRole::ADMINISTRATOR->value]);
        $this->assertDatabaseCount('user_warehouse', 0);
    }

    public function test_an_administrator_can_create_a_warehouse_manager_with_one_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->storeUser(UserRole::WAREHOUSE_MANAGER, [$warehouse->id])->assertSessionHasNoErrors();

        $user = User::where('email', 'new.user@example.com')->firstOrFail();
        $this->assertEquals([$warehouse->id], $user->warehouses()->pluck('warehouses.id')->all());
    }

    public function test_an_administrator_can_create_a_warehouse_manager_with_multiple_warehouses(): void
    {
        $warehouses = Warehouse::factory()->count(2)->create();

        $this->storeUser(UserRole::WAREHOUSE_MANAGER, $warehouses->modelKeys())->assertSessionHasNoErrors();

        $this->assertCount(2, User::where('email', 'new.user@example.com')->firstOrFail()->warehouses);
    }

    public function test_an_administrator_cannot_create_a_nurse_with_multiple_warehouses(): void
    {
        $warehouses = Warehouse::factory()->count(2)->create();

        $this->storeUser(UserRole::NURSE, $warehouses->modelKeys())
            ->assertSessionHasErrors(['warehouse_ids' => 'Una enfermera sólo puede estar asignada a un almacén.']);
    }

    public function test_warehouse_manager_requires_a_warehouse_but_nurse_does_not(): void
    {
        $this->storeUser(UserRole::WAREHOUSE_MANAGER)->assertSessionHasErrors('warehouse_ids');
        $this->storeUser(UserRole::NURSE)->assertSessionHasNoErrors();
    }

    public function test_warehouses_sent_for_an_administrator_are_ignored(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->storeUser(UserRole::ADMINISTRATOR, [$warehouse->id])->assertSessionHasNoErrors();

        $user = User::where('email', 'new.user@example.com')->firstOrFail();
        $this->assertCount(0, $user->warehouses);
    }

    public function test_root_and_legacy_roles_cannot_be_created_through_the_crud(): void
    {
        foreach ([UserRole::ROOT, UserRole::LEGACY_USER] as $role) {
            $this->storeUser($role)->assertSessionHasErrors('role');
        }
    }

    public function test_email_must_be_unique(): void
    {
        $existing = User::factory()->create();

        $this->storeUser(UserRole::ADMINISTRATOR, [], ['email' => $existing->email])
            ->assertSessionHasErrors('email');
    }

    public function test_selected_warehouses_must_exist_and_be_distinct(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->storeUser(UserRole::WAREHOUSE_MANAGER, [999999])->assertSessionHasErrors('warehouse_ids.0');
        $this->storeUser(UserRole::WAREHOUSE_MANAGER, [$warehouse->id, $warehouse->id])->assertSessionHasErrors('warehouse_ids.1');
    }

    public function test_password_is_stored_hashed(): void
    {
        $this->storeUser(UserRole::ADMINISTRATOR)->assertSessionHasNoErrors();

        $user = User::where('email', 'new.user@example.com')->firstOrFail();
        $this->assertNotSame('SecurePassword123', $user->password);
        $this->assertTrue(Hash::check('SecurePassword123', $user->password));
    }

    public function test_a_user_can_be_updated_with_valid_data(): void
    {
        [$administrator, $user] = $this->administratorAndManagedUser();
        $warehouse = Warehouse::factory()->create();

        $response = $this->actingAs($administrator)->put(route('users.update', $user), $this->updatePayload([
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'role' => UserRole::NURSE->value,
            'warehouse_ids' => [$warehouse->id],
        ]));

        $response->assertRedirect(route('users.index'))->assertSessionHas('success', 'Usuario actualizado correctamente.');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated', 'email' => 'updated@example.com']);
        $this->assertTrue($user->fresh()->warehouses->contains($warehouse));
    }

    public function test_updating_without_a_password_preserves_the_existing_password(): void
    {
        [$administrator, $user] = $this->administratorAndManagedUser();
        $password = $user->password;

        $this->actingAs($administrator)->put(route('users.update', $user), $this->updatePayload())->assertSessionHasNoErrors();

        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_updating_with_a_password_changes_it(): void
    {
        [$administrator, $user] = $this->administratorAndManagedUser();

        $this->actingAs($administrator)->put(route('users.update', $user), $this->updatePayload([
            'password' => 'ChangedPassword123',
            'password_confirmation' => 'ChangedPassword123',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('ChangedPassword123', $user->fresh()->password));
    }

    public function test_changing_a_warehouse_manager_to_administrator_removes_warehouse_assignments(): void
    {
        [$administrator, $user] = $this->administratorAndManagedUser();
        $user->warehouses()->attach(Warehouse::factory()->create());

        $this->actingAs($administrator)->put(route('users.update', $user), $this->updatePayload([
            'role' => UserRole::ADMINISTRATOR->value,
        ]))->assertSessionHasNoErrors();

        $this->assertCount(0, $user->fresh()->warehouses);
    }

    public function test_changing_an_administrator_to_warehouse_manager_requires_a_warehouse(): void
    {
        $actor = User::factory()->administrator()->create();
        $target = User::factory()->administrator()->create();

        $this->actingAs($actor)->put(route('users.update', $target), $this->updatePayload([
            'email' => $target->email,
            'role' => UserRole::WAREHOUSE_MANAGER->value,
        ]))->assertSessionHasErrors('warehouse_ids');
    }

    public function test_a_legacy_user_can_be_converted_to_a_valid_role(): void
    {
        $administrator = User::factory()->administrator()->create();
        $legacyUser = User::factory()->create(['role' => UserRole::LEGACY_USER]);
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($administrator)->put(route('users.update', $legacyUser), $this->updatePayload([
            'email' => $legacyUser->email,
            'role' => UserRole::WAREHOUSE_MANAGER->value,
            'warehouse_ids' => [$warehouse->id],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(UserRole::WAREHOUSE_MANAGER, $legacyUser->fresh()->role);
    }

    public function test_a_legacy_user_cannot_keep_the_legacy_role_when_updated(): void
    {
        $administrator = User::factory()->administrator()->create();
        $legacyUser = User::factory()->create(['role' => UserRole::LEGACY_USER]);

        $this->actingAs($administrator)->put(route('users.update', $legacyUser), $this->updatePayload([
            'email' => $legacyUser->email,
            'role' => UserRole::LEGACY_USER->value,
        ]))->assertSessionHasErrors('role');
    }

    public function test_an_administrator_can_delete_another_user(): void
    {
        [$administrator, $user] = $this->administratorAndManagedUser();

        $this->actingAs($administrator)->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'Usuario eliminado correctamente.');

        $this->assertModelMissing($user);
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->delete(route('users.destroy', $administrator))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error', 'No puedes eliminar tu propio usuario.');

        $this->assertModelExists($administrator);
    }

    public function test_root_cannot_be_deleted_or_edited_through_the_crud(): void
    {
        $administrator = User::factory()->administrator()->create();
        $root = User::factory()->create(['role' => UserRole::ROOT]);

        $this->actingAs($administrator)->get(route('users.edit', $root))->assertForbidden();
        $this->actingAs($administrator)->put(route('users.update', $root), $this->updatePayload([
            'email' => $root->email,
        ]))->assertForbidden();
        $this->actingAs($administrator)->delete(route('users.destroy', $root))->assertForbidden();
        $this->assertModelExists($root);
    }

    private function storeUser(UserRole $role, array $warehouseIds = [], array $overrides = []): TestResponse
    {
        $administrator = User::factory()->administrator()->create();

        return $this->actingAs($administrator)->post(route('users.store'), array_merge([
            'name' => 'New',
            'last_name_one' => 'User',
            'last_name_two' => null,
            'email' => 'new.user@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
            'role' => $role->value,
            'warehouse_ids' => $warehouseIds,
        ], $overrides));
    }

    /**
     * @return array{User, User}
     */
    private function administratorAndManagedUser(): array
    {
        return [
            User::factory()->administrator()->create(),
            User::factory()->warehouseManager()->create(),
        ];
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Managed',
            'last_name_one' => 'User',
            'last_name_two' => null,
            'email' => 'managed.updated@example.com',
            'role' => UserRole::ADMINISTRATOR->value,
            'warehouse_ids' => [],
        ], $overrides);
    }
}
