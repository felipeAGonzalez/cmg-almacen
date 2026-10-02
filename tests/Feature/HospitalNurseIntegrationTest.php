<?php

namespace Tests\Feature;

use App\Contracts\HospitalNurseProvider;
use App\Data\HospitalNurse;
use App\Exceptions\HospitalIntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HospitalNurseIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hospital.url' => 'https://hospital.example.test',
            'hospital.token' => 'test-secret-token',
            'hospital.timeout' => 7,
        ]);
    }

    public function test_valid_response_is_authenticated_and_mapped_to_dto(): void
    {
        Http::fake([
            'https://hospital.example.test/api/integrations/warehouse/nurses' => Http::response(['data' => [[
                'user_id' => 27,
                'name' => ' María López ',
                'email' => ' maria@example.com ',
                'ignored' => 'value',
            ]]]),
        ]);

        $nurse = app(HospitalNurseProvider::class)->nurses()->sole();

        $this->assertInstanceOf(HospitalNurse::class, $nurse);
        $this->assertSame('27', $nurse->hospitalUserId);
        $this->assertSame('María López', $nurse->name);
        $this->assertSame('maria@example.com', $nurse->email);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://hospital.example.test/api/integrations/warehouse/nurses'
            && $request->hasHeader('Authorization', 'Bearer test-secret-token')
            && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_empty_list_is_valid(): void
    {
        Http::fake(fn () => Http::response(['data' => []]));

        $this->assertTrue(app(HospitalNurseProvider::class)->nurses()->isEmpty());
    }

    public function test_remote_authentication_server_and_connection_errors_are_controlled(): void
    {
        Http::fake(fn () => Http::response([], 401));
        $this->expectControlledFailure();

        Http::fake(fn () => Http::response([], 500));
        $this->expectControlledFailure();

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->expectControlledFailure();
    }

    public function test_invalid_json_and_missing_fields_are_rejected(): void
    {
        $payloads = [
            '{invalid-json',
            ['data' => [['name' => 'María', 'email' => 'maria@example.com']]],
            ['data' => [['user_id' => '27', 'email' => 'maria@example.com']]],
            ['data' => [['user_id' => '27', 'name' => 'María']]],
            ['data' => [['user_id' => '', 'name' => 'María', 'email' => 'maria@example.com']]],
        ];

        foreach ($payloads as $payload) {
            Http::fake(fn () => Http::response($payload, 200, ['Content-Type' => 'application/json']));
            $this->expectControlledFailure();
        }
    }

    private function expectControlledFailure(): void
    {
        try {
            app(HospitalNurseProvider::class)->nurses();
            $this->fail('Expected HospitalIntegrationException was not thrown.');
        } catch (HospitalIntegrationException $exception) {
            $this->assertStringNotContainsString('test-secret-token', $exception->safeMessage);
        }
    }
}
