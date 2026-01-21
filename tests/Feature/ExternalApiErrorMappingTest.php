<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalApiErrorMappingTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_maps_upstream_422_validation_error(): void
    {
        Http::fake([
            '*' => Http::response(
                ['errors' => ['email' => ['Required']]],
                422
            )
        ]);

        $response = $this->getJson('/api/external/demo');

        $response
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'UPSTREAM_VALIDATION_FAILED')
            ->assertJsonPath('error.retryable', false)
            ->assertJsonStructure([
                'error' => [
                    'meta' => ['upstream_body']
                ]
                ]);
    }

    public function test_maps_upstream_429_rate_limit(): void
    {
        Http::fake([
            '*' => Http::response(
                ['error' => 'Too many requests'],
                429,
                ['Retry-After' => '30']
            ),
        ]);

        $response = $this->getJson('/api/external/demo');

        $response
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'UPSTREAM_RATE_LIMITED')
            ->assertJsonPath('error.retryable', true)
            ->assertJsonPath('error.meta.retry_after', '30');
    }
    
    public function test_maps_upstream_503_unavailable(): void
    {
        Http::fake([
            '*' => Http::response(
                ['error' => 'Service unavailable'],
                503
            ),
        ]);

        $response = $this->getJson('/api/external/demo');

        $response
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'UPSTREAM_UNAVAILABLE')
            ->assertJsonPath('error.retryable', true);
    }

    public function test_maps_timeout_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Operation timed out');
        });

        $response = $this->getJson('/api/external/demo');

        $response
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'UPSTREAM_TIMEOUT')
            ->assertJsonPath('error.retryable', true);
    }

    public function test_maps_connection_exception(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('DNS failure');
        });

        $response = $this->getJson('/api/external/demo');

        $response
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'UPSTREAM_CONNECTION_FAILED')
            ->assertJsonPath('error.retryable', true);
    }
}
