<?php

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationPriority;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_queue_depth_by_priority(): void
    {
        Notification::factory()->pending()->count(2)->create(['priority' => NotificationPriority::High]);
        Notification::factory()->queued()->count(3)->create(['priority' => NotificationPriority::Normal]);
        Notification::factory()->sent()->count(5)->create();

        $response = $this->getJson('/api/v1/metrics');

        $response->assertOk()
            ->assertJsonPath('data.queue_depth.high', 2)
            ->assertJsonPath('data.queue_depth.normal', 3)
            ->assertJsonPath('data.queue_depth.low', 0)
            ->assertJsonStructure([
                'data' => [
                    'queue_depth' => ['high', 'normal', 'low'],
                    'realtime' => ['totals', 'by_channel', 'latency_ms'],
                ],
                'meta' => ['generated_at'],
            ]);
    }
}
