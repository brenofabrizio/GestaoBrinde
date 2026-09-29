<?php

namespace Tests\Feature;

use Tests\TestCase;

final class CorsHeadersTest extends TestCase
{
    public function test_configured_frontend_origin_gets_cors_headers(): void
    {
        $origin = config('cors.allowed_origins')[0];

        $this->withHeaders([
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/health')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_unconfigured_origin_is_not_reflected(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://not-allowed.example',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])->options('/api/v1/health')->assertNoContent();

        $this->assertNotSame('https://not-allowed.example', $response->headers->get('Access-Control-Allow-Origin'));
    }
}
