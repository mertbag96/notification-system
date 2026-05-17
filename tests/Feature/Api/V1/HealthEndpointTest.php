<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_healthy_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonStructure([
                'data' => ['status', 'checks' => ['database', 'cache', 'queue']],
                'meta' => ['app', 'env', 'time'],
            ]);
    }
}
