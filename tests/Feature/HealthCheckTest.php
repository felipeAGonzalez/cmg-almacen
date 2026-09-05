<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_is_public_and_returns_no_sensitive_data(): void
    {
        $response = $this->getJson('/health');

        $response
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }
}
