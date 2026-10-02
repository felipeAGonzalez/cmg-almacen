<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HospitalNurseOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hospital.url' => 'https://hospital.example.test',
            'hospital.token' => 'test-token',
        ]);
    }

    public function test_administrator_and_root_can_load_available_hospital_nurses(): void
    {
        $this->fakeNurses();

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT] as $role) {
            $response = $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(route('users.hospital-nurses'));

            $response->assertOk()
                ->assertJsonPath('data.0.hospital_user_id', 'hospital-1')
                ->assertJsonPath('data.0.name', 'María López')
                ->assertJsonPath('data.0.email', 'maria@example.com')
                ->assertJsonPath('meta.total', 2);
        }
    }

    public function test_non_administrative_users_and_guests_cannot_load_options(): void
    {
        foreach ([UserRole::WAREHOUSE_MANAGER, UserRole::NURSE, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(route('users.hospital-nurses'))
                ->assertForbidden();
        }

        auth()->logout();
        $this->getJson(route('users.hospital-nurses'))->assertRedirect(route('login'));
    }

    public function test_linked_nurses_are_excluded_but_the_edited_users_link_is_kept(): void
    {
        $this->fakeNurses();
        User::factory()->nurse()->create(['hospital_user_id' => 'hospital-1']);
        $edited = User::factory()->nurse()->create(['hospital_user_id' => 'hospital-2']);
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)
            ->getJson(route('users.hospital-nurses'))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->getJson(route('users.hospital-nurses', ['user_id' => $edited->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.hospital_user_id', 'hospital-2')
            ->assertJsonPath('data.0.is_current', true);
    }

    public function test_hospital_failures_return_a_safe_controlled_response(): void
    {
        $fakes = [
            fn () => Http::response([], 500),
            fn () => Http::response([], 401),
            fn () => Http::response('{invalid', 200),
            fn () => throw new ConnectionException('timeout'),
        ];

        $administrator = User::factory()->administrator()->create();
        foreach ($fakes as $fake) {
            Http::fake($fake);
            $this->actingAs($administrator)
                ->getJson(route('users.hospital-nurses'))
                ->assertStatus(503)
                ->assertExactJson([
                    'message' => 'No fue posible consultar las enfermeras de Hospitalización.',
                ]);
        }
    }

    public function test_user_form_contains_asynchronous_accessible_linking_interface(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->get(route('users.create'))
            ->assertOk()
            ->assertSee('Vinculación con Hospitalización')
            ->assertSee('data-hospital-nurse-status', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('La vinculación con Hospitalización se eliminará al guardar.')
            ->assertSee('data-user-submit', false);
    }

    private function fakeNurses(): void
    {
        Http::fake(fn () => Http::response(['data' => [
            ['user_id' => 'hospital-1', 'name' => 'María López', 'email' => 'maria@example.com'],
            ['user_id' => 'hospital-2', 'name' => 'Ana Pérez', 'email' => 'ana@example.com'],
        ]]));
    }
}
