<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_unknown_route_is_a_404_envelope_even_without_accept_header(): void
    {
        $this->get('/api/v1/nope')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('status', 'fail')
            ->assertJsonPath('hasError', true);
    }

    public function test_wrong_method_is_a_405_envelope(): void
    {
        $this->deleteJson('/api/v1/auth/login')->assertStatus(405)->assertJsonPath('status', 'fail');
    }

    public function test_server_errors_hide_details_in_production(): void
    {
        Route::get('/api/test-crash', fn () => throw new RuntimeException('SQLSTATE secret details'));

        config(['app.debug' => false]);
        $this->getJson('/api/test-crash')
            ->assertStatus(500)
            ->assertJsonPath('errors.0', 'Server error. Please try again later.')
            ->assertDontSee('SQLSTATE');

        config(['app.debug' => true]);
        $this->getJson('/api/test-crash')->assertStatus(500)->assertJsonPath('errors.0', 'SQLSTATE secret details');
    }

    public function test_security_headers_are_sent(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_cors_allows_only_the_frontend_origin(): void
    {
        $allowed = config('cors.allowed_origins')[0];

        $this->withHeaders(['Origin' => $allowed, 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/v1/auth/me')
            ->assertHeader('Access-Control-Allow-Origin', $allowed);

        $response = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'GET'])
            ->options('/api/v1/auth/me');

        $this->assertNotSame('https://evil.example', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_root_describes_the_api(): void
    {
        $this->getJson('/')->assertOk()->assertJsonStructure(['name', 'api', 'docs', 'health']);
    }
}
