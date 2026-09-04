<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HospitalDelegatedAuthTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'feature-test-delegated-secret-that-is-independent';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'hospital.delegated_auth.secret' => self::SECRET,
            'hospital.delegated_auth.ttl' => 60,
            'hospital.delegated_auth.audience' => 'cmg-warehouse',
            'hospital.delegated_auth.clock_skew' => 5,
        ]);
        Cache::flush();
    }

    public function test_linked_nurse_can_consume_token_and_receives_minimal_context(): void
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE, 'hospital_user_id' => 'nurse-77']);
        $token = $this->token();

        $response = $this->get(route('hospital-delegated-auth.consume', ['token' => $token]));

        $response->assertRedirect(route('nursing.hospital-context'))
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSessionHas('hospital_context', [
                'patient_id' => 'patient-10',
                'hospitalization_id' => 'stay-20',
                'room_id' => 'room-7',
                'room_number' => '204',
            ])
            ->assertSessionMissing('token')
            ->assertSessionMissing('nonce');
        $this->assertAuthenticatedAs($nurse);
    }

    public function test_context_screen_requires_an_authenticated_nurse(): void
    {
        $route = route('nursing.hospital-context');
        $this->get($route)->assertRedirect(route('login'));

        foreach ([UserRole::ADMINISTRATOR, UserRole::ROOT, UserRole::WAREHOUSE_MANAGER, UserRole::LEGACY_USER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($route)->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => UserRole::NURSE]))
            ->withSession(['hospital_context' => [
                'patient_id' => 'patient-10', 'hospitalization_id' => 'stay-20',
                'room_id' => 'room-7', 'room_number' => '204',
            ]])->get($route)->assertOk()
            ->assertSee('Acceso desde Hospitalización')
            ->assertSee('ID hospitalización')
            ->assertSee('204');
    }

    public function test_unlinked_user_is_not_created_or_authenticated(): void
    {
        $this->get(route('hospital-delegated-auth.consume', ['token' => $this->token()]))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tu cuenta de Hospitalización todavía no está vinculada con una cuenta de Almacén.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_linked_local_non_nurse_is_rejected(): void
    {
        User::factory()->administrator()->create(['hospital_user_id' => 'nurse-77']);

        $this->get(route('hospital-delegated-auth.consume', ['token' => $this->token()]))
            ->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_invalid_hospital_role_expired_and_invalid_signature_are_rejected(): void
    {
        User::factory()->create(['role' => UserRole::NURSE, 'hospital_user_id' => 'nurse-77']);
        $now = now()->timestamp;

        $tokens = [
            $this->token(['role' => 'physician']),
            $this->token(['iat' => $now - 100, 'exp' => $now - 10]),
            $this->token([], 'wrong-secret'),
        ];

        foreach ($tokens as $token) {
            $this->get(route('hospital-delegated-auth.consume', ['token' => $token]))
                ->assertRedirect(route('login'))->assertSessionHas('error');
            $this->assertGuest();
        }
    }

    public function test_missing_token_is_rejected_without_leaking_it(): void
    {
        $this->get(route('hospital-delegated-auth.consume'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'El acceso desde Hospitalización no es válido o ha expirado.');
        $this->assertGuest();
    }

    public function test_replay_fails_but_a_distinct_nonce_works(): void
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE, 'hospital_user_id' => 'nurse-77']);
        $token = $this->token(['nonce' => 'first-nonce']);

        $this->get(route('hospital-delegated-auth.consume', ['token' => $token]))->assertRedirect(route('nursing.hospital-context'));
        $this->get(route('hospital-delegated-auth.consume', ['token' => $token]))->assertRedirect(route('home'))->assertSessionHas('error');
        $this->get(route('hospital-delegated-auth.consume', ['token' => $this->token(['nonce' => 'second-nonce'])]))
            ->assertRedirect(route('nursing.hospital-context'));
        $this->assertAuthenticatedAs($nurse);
    }

    public function test_same_authenticated_user_can_continue_but_different_user_is_not_replaced(): void
    {
        $nurse = User::factory()->create(['role' => UserRole::NURSE, 'hospital_user_id' => 'nurse-77']);
        $this->actingAs($nurse)->get(route('hospital-delegated-auth.consume', ['token' => $this->token()]))
            ->assertRedirect(route('nursing.hospital-context'));
        $this->assertAuthenticatedAs($nurse);

        Cache::flush();
        $other = User::factory()->create(['role' => UserRole::NURSE, 'hospital_user_id' => 'other-nurse']);
        $this->actingAs($other)->get(route('hospital-delegated-auth.consume', ['token' => $this->token(['nonce' => 'other-session-nonce'])]))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error', 'Cierra la sesión actual antes de acceder con otra cuenta de Hospitalización.');
        $this->assertAuthenticatedAs($other);
    }

    private function token(array $overrides = [], string $secret = self::SECRET): string
    {
        $now = now()->timestamp;
        $payload = array_merge([
            'hospital_user_id' => 'nurse-77',
            'role' => 'nurse',
            'patient_id' => 'patient-10',
            'hospitalization_id' => 'stay-20',
            'room_id' => 'room-7',
            'room_number' => '204',
            'iat' => $now,
            'exp' => $now + 60,
            'nonce' => fake()->uuid(),
            'aud' => 'cmg-warehouse',
        ], $overrides);
        $encoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, $secret, true);

        return $encoded.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
