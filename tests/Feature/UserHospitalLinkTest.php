<?php

namespace Tests\Feature;

use App\Contracts\HospitalNurseProvider;
use App\Enums\UserRole;
use App\Exceptions\HospitalIntegrationException;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UserHospitalLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['hospital.url' => 'https://hospital.example.test']);
        $this->fakeHospitalNurses();
    }

    public function test_nurse_can_have_trimmed_optional_hospital_user_id(): void
    {
        $warehouse = Warehouse::factory()->create();
        $this->actingAs(User::factory()->administrator()->create())->post(route('users.store'), $this->payload([
            'role' => UserRole::NURSE->value,
            'warehouse_ids' => [$warehouse->id],
            'hospital_user_id' => '  external-nurse-42  ',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'linked@example.com', 'hospital_user_id' => 'external-nurse-42']);
    }

    public function test_hospital_user_id_is_unique_for_nurses(): void
    {
        User::factory()->create(['role' => UserRole::NURSE, 'hospital_user_id' => 'duplicate-id']);
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())->post(route('users.store'), $this->payload([
            'role' => UserRole::NURSE->value,
            'warehouse_ids' => [$warehouse->id],
            'hospital_user_id' => 'duplicate-id',
        ]))->assertSessionHasErrors('hospital_user_id');
    }

    public function test_non_nurse_roles_do_not_retain_submitted_hospital_link(): void
    {
        foreach ([UserRole::ADMINISTRATOR, UserRole::WAREHOUSE_MANAGER] as $index => $role) {
            $warehouseIds = $role === UserRole::WAREHOUSE_MANAGER ? [Warehouse::factory()->create()->id] : [];
            $this->actingAs(User::factory()->administrator()->create())->post(route('users.store'), $this->payload([
                'email' => "role-{$index}@example.com",
                'role' => $role->value,
                'warehouse_ids' => $warehouseIds,
                'hospital_user_id' => "should-be-cleared-{$index}",
            ]))->assertSessionHasNoErrors();

            $this->assertNull(User::where('email', "role-{$index}@example.com")->value('hospital_user_id'));
        }
    }

    public function test_changing_nurse_to_another_role_clears_link(): void
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE, 'hospital_user_id' => 'old-link']);

        $this->actingAs(User::factory()->administrator()->create())->put(route('users.update', $nurse), $this->payload([
            'email' => $nurse->email,
            'role' => UserRole::ADMINISTRATOR->value,
            'hospital_user_id' => 'old-link',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($nurse->fresh()->hospital_user_id);
    }

    public function test_nurse_without_hospital_link_remains_valid_and_form_shows_selector(): void
    {
        $warehouse = Warehouse::factory()->create();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->post(route('users.store'), $this->payload([
            'role' => UserRole::NURSE->value,
            'warehouse_ids' => [$warehouse->id],
            'hospital_user_id' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(User::where('email', 'linked@example.com')->value('hospital_user_id'));
        $this->get(route('users.create'))
            ->assertOk()
            ->assertSee('Usuario de Hospitalización')
            ->assertSee('Vinculación con Hospitalización');
    }

    public function test_manipulated_hospital_user_id_is_rejected(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())->post(route('users.store'), $this->payload([
            'role' => UserRole::NURSE->value,
            'warehouse_ids' => [$warehouse->id],
            'hospital_user_id' => 'not-a-hospital-nurse',
        ]))->assertSessionHasErrors('hospital_user_id');

        $this->assertDatabaseMissing('users', ['email' => 'linked@example.com']);
    }

    public function test_edit_keeps_current_hospital_nurse_available(): void
    {
        $nurse = User::factory()->create([
            'role' => UserRole::NURSE,
            'hospital_user_id' => 'old-link',
        ]);

        $this->actingAs(User::factory()->administrator()->create());

        $this->get(route('users.edit', $nurse))
            ->assertOk()
            ->assertSee('data-current-hospital-user-id="old-link"', false);

        $this->getJson(route('users.hospital-nurses', ['user_id' => $nurse->id]))
            ->assertOk()
            ->assertJsonFragment([
                'hospital_user_id' => 'old-link',
                'name' => 'Enfermera Actual',
                'email' => 'current@example.com',
                'is_current' => true,
            ]);
    }

    public function test_linked_nurses_are_excluded_from_create_selector(): void
    {
        User::factory()->create([
            'role' => UserRole::NURSE,
            'hospital_user_id' => 'duplicate-id',
        ]);

        $this->actingAs(User::factory()->administrator()->create())
            ->getJson(route('users.hospital-nurses'))
            ->assertOk()
            ->assertJsonMissing(['hospital_user_id' => 'duplicate-id']);
    }

    public function test_hospital_unavailable_does_not_break_form_and_preserves_current_link(): void
    {
        $nurse = User::factory()->create([
            'role' => UserRole::NURSE,
            'hospital_user_id' => 'old-link',
        ]);
        $this->app->instance(HospitalNurseProvider::class, new class implements HospitalNurseProvider
        {
            public function nurses(): Collection
            {
                throw HospitalIntegrationException::unavailable();
            }
        });

        $this->actingAs(User::factory()->administrator()->create())
            ->getJson(route('users.hospital-nurses', ['user_id' => $nurse->id]))
            ->assertStatus(503)
            ->assertJsonPath('message', 'No fue posible consultar las enfermeras de Hospitalización.');

        $this->put(route('users.update', $nurse), $this->payload([
            'email' => $nurse->email,
            'hospital_user_id' => 'old-link',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('old-link', $nurse->fresh()->hospital_user_id);
    }

    private function fakeHospitalNurses(): void
    {
        Http::fake([
            'https://hospital.example.test/api/integrations/warehouse/nurses' => Http::response(['data' => [
                ['user_id' => 'external-nurse-42', 'name' => 'Enfermera Hospitalaria', 'email' => 'nurse@example.com'],
                ['user_id' => 'duplicate-id', 'name' => 'Enfermera Vinculada', 'email' => 'linked@example.com'],
                ['user_id' => 'old-link', 'name' => 'Enfermera Actual', 'email' => 'current@example.com'],
            ]]),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Linked',
            'last_name_one' => 'Nurse',
            'last_name_two' => null,
            'email' => 'linked@example.com',
            'password' => 'SecurePassword123',
            'password_confirmation' => 'SecurePassword123',
            'role' => UserRole::NURSE->value,
            'warehouse_ids' => [],
            'hospital_user_id' => null,
        ], $overrides);
    }
}
