<?php

namespace Tests\Unit;

use App\Exceptions\HospitalDelegatedAuthException;
use App\Services\HospitalDelegatedAuthService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HospitalDelegatedAuthServiceTest extends TestCase
{
    private const SECRET = 'unit-test-delegated-secret-that-is-independent';

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

    public function test_valid_token_returns_normalized_context(): void
    {
        $context = $this->service()->consume($this->token([
            'hospital_user_id' => 42,
            'patient_id' => 100,
            'hospitalization_id' => 200,
            'room_id' => 7,
            'room_number' => 204,
        ]));

        $this->assertSame('42', $context->hospitalUserId);
        $this->assertSame('100', $context->patientId);
        $this->assertSame('200', $context->hospitalizationId);
        $this->assertSame('7', $context->roomId);
        $this->assertSame('204', $context->roomNumber);
    }

    public function test_invalid_signature_and_malformed_tokens_are_rejected(): void
    {
        $this->assertRejected($this->token([], 'wrong-secret'), 'invalid_signature');
        $this->assertRejected('not-a-valid-token', 'malformed_token');
        $this->assertRejected('%%%.___', 'invalid_signature');
    }

    public function test_missing_secret_is_rejected(): void
    {
        config(['hospital.delegated_auth.secret' => '']);
        $this->assertRejected($this->token(), 'missing_secret');
    }

    public function test_invalid_audience_and_role_are_rejected(): void
    {
        $this->assertRejected($this->token(['aud' => 'another-system']), 'invalid_audience');
        $this->assertRejected($this->token(['role' => 'administrator']), 'invalid_role');
    }

    public function test_expired_invalid_range_excessive_ttl_and_future_tokens_are_rejected(): void
    {
        $now = now()->timestamp;
        $this->assertRejected($this->token(['iat' => $now - 50, 'exp' => $now - 10]), 'expired_token');
        $this->assertRejected($this->token(['iat' => $now, 'exp' => $now]), 'invalid_time_range');
        $this->assertRejected($this->token(['iat' => $now, 'exp' => $now + 61]), 'excessive_ttl');
        $this->assertRejected($this->token(['iat' => $now + 6, 'exp' => $now + 60]), 'future_token');
    }

    public function test_nonce_and_required_claims_are_validated(): void
    {
        $this->assertRejected($this->token(['nonce' => '']), 'invalid_claim');
        $payload = $this->payload();
        unset($payload['patient_id']);
        $this->assertRejected($this->sign($payload), 'missing_claim');
    }

    public function test_nonce_is_one_time(): void
    {
        $token = $this->token(['nonce' => 'single-use-nonce']);
        $this->service()->consume($token);
        $this->assertRejected($token, 'replayed_token');
    }

    private function service(): HospitalDelegatedAuthService
    {
        return app(HospitalDelegatedAuthService::class);
    }

    private function assertRejected(string $token, string $reason): void
    {
        try {
            $this->service()->consume($token);
            $this->fail('The token should have been rejected.');
        } catch (HospitalDelegatedAuthException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }

    private function token(array $overrides = [], string $secret = self::SECRET): string
    {
        return $this->sign(array_merge($this->payload(), $overrides), $secret);
    }

    private function payload(): array
    {
        $now = now()->timestamp;

        return [
            'hospital_user_id' => 'hospital-user-1',
            'role' => 'nurse',
            'patient_id' => 'patient-1',
            'hospitalization_id' => 'hospitalization-1',
            'room_id' => 'room-1',
            'room_number' => '204',
            'iat' => $now,
            'exp' => $now + 60,
            'nonce' => fake()->uuid(),
            'aud' => 'cmg-warehouse',
        ];
    }

    private function sign(array $payload, string $secret = self::SECRET): string
    {
        $encoded = $this->base64Url(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encoded, $secret, true);

        return $encoded.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
