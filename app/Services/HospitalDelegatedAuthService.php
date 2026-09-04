<?php

namespace App\Services;

use App\Data\HospitalDelegatedAuthContext;
use App\Exceptions\HospitalDelegatedAuthException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use JsonException;

class HospitalDelegatedAuthService
{
    private const REQUIRED_CLAIMS = [
        'hospital_user_id', 'role', 'patient_id', 'hospitalization_id',
        'room_id', 'room_number', 'iat', 'exp', 'nonce', 'aud',
    ];

    public function __construct(private readonly CacheRepository $cache) {}

    public function consume(string $token): HospitalDelegatedAuthContext
    {
        $secret = (string) config('hospital.delegated_auth.secret', '');

        if ($secret === '') {
            throw new HospitalDelegatedAuthException('missing_secret');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new HospitalDelegatedAuthException('malformed_token');
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $signature = $this->base64UrlDecode($encodedSignature);
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret, true);

        if ($signature === null || ! hash_equals($expectedSignature, $signature)) {
            throw new HospitalDelegatedAuthException('invalid_signature');
        }

        $decodedPayload = $this->base64UrlDecode($encodedPayload);
        if ($decodedPayload === null) {
            throw new HospitalDelegatedAuthException('malformed_payload');
        }

        try {
            $payload = json_decode($decodedPayload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HospitalDelegatedAuthException('malformed_payload');
        }

        if (! is_array($payload) || collect(self::REQUIRED_CLAIMS)->contains(fn (string $claim): bool => ! array_key_exists($claim, $payload))) {
            throw new HospitalDelegatedAuthException('missing_claim');
        }

        $audience = (string) config('hospital.delegated_auth.audience', 'cmg-warehouse');
        if (! is_string($payload['aud']) || ! hash_equals($audience, $payload['aud'])) {
            throw new HospitalDelegatedAuthException('invalid_audience');
        }

        if ($payload['role'] !== 'nurse') {
            throw new HospitalDelegatedAuthException('invalid_role');
        }

        $issuedAt = $this->integerClaim($payload['iat']);
        $expiresAt = $this->integerClaim($payload['exp']);
        $ttl = max(1, (int) config('hospital.delegated_auth.ttl', 60));
        $clockSkew = max(0, (int) config('hospital.delegated_auth.clock_skew', 5));
        $now = now()->timestamp;

        if ($expiresAt <= $issuedAt) {
            throw new HospitalDelegatedAuthException('invalid_time_range');
        }
        if (($expiresAt - $issuedAt) > $ttl) {
            throw new HospitalDelegatedAuthException('excessive_ttl');
        }
        if ($issuedAt > ($now + $clockSkew)) {
            throw new HospitalDelegatedAuthException('future_token');
        }
        if ($now > ($expiresAt + $clockSkew)) {
            throw new HospitalDelegatedAuthException('expired_token');
        }

        $nonce = $this->stringClaim($payload['nonce']);
        if (strlen($nonce) > 255) {
            throw new HospitalDelegatedAuthException('invalid_nonce');
        }

        $hospitalUserId = $this->stringClaim($payload['hospital_user_id']);
        $patientId = $this->stringClaim($payload['patient_id']);
        $hospitalizationId = $this->stringClaim($payload['hospitalization_id']);
        $roomId = $this->stringClaim($payload['room_id']);
        $roomNumber = $this->stringClaim($payload['room_number']);

        $cacheKey = 'hospital-delegated-auth:'.hash('sha256', $nonce);
        $expires = CarbonImmutable::createFromTimestamp($expiresAt + $clockSkew);
        if (! $this->cache->add($cacheKey, true, $expires)) {
            throw new HospitalDelegatedAuthException('replayed_token');
        }

        return new HospitalDelegatedAuthContext(
            hospitalUserId: $hospitalUserId,
            patientId: $patientId,
            hospitalizationId: $hospitalizationId,
            roomId: $roomId,
            roomNumber: $roomNumber,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
        );
    }

    private function stringClaim(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new HospitalDelegatedAuthException('invalid_claim');
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            throw new HospitalDelegatedAuthException('invalid_claim');
        }

        return $normalized;
    }

    private function integerClaim(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new HospitalDelegatedAuthException('invalid_time_claim');
    }

    private function base64UrlDecode(string $value): ?string
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', $padding), true);

        return $decoded === false ? null : $decoded;
    }
}
