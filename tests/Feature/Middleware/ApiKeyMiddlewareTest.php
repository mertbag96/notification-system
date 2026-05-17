<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_with_valid_header_key_is_authorized(): void
    {
        config()->set('notifications.api.key', 'local-development-key');

        $this->withHeader('X-Api-Key', 'local-development-key')
            ->getJson('/api/v1/health')
            ->assertOk();
    }

    public function test_request_with_valid_query_parameter_key_is_authorized(): void
    {
        config()->set('notifications.api.key', 'local-development-key');

        $this->getJson('/api/v1/health?api_key=local-development-key')
            ->assertOk();
    }

    public function test_request_with_missing_credentials_is_rejected(): void
    {
        config()->set('notifications.api.key', 'local-development-key');

        $this->getJson('/api/v1/health')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'unauthorized');
    }

    public function test_request_with_invalid_query_parameter_key_is_rejected(): void
    {
        config()->set('notifications.api.key', 'local-development-key');

        $this->getJson('/api/v1/health?api_key=not-the-real-key')
            ->assertUnauthorized()
            ->assertJsonPath('errors.0.code', 'unauthorized');
    }

    public function test_header_takes_precedence_over_query_parameter(): void
    {
        config()->set('notifications.api.key', 'local-development-key');

        $this->withHeader('X-Api-Key', 'local-development-key')
            ->getJson('/api/v1/health?api_key=wrong')
            ->assertOk();
    }
}
