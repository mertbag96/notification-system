<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateBatchNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_batch_of_notifications(): void
    {
        Queue::fake();

        $payload = [
            'notifications' => [
                ['channel' => 'sms', 'recipient' => '+14155550100', 'content' => 'A'],
                ['channel' => 'email', 'recipient' => 'a@example.com', 'content' => 'B'],
                ['channel' => 'push', 'recipient' => str_repeat('x', 32), 'content' => 'C'],
            ],
        ];

        $response = $this->postJson('/api/v1/notifications/batch', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.accepted', 3)
            ->assertJsonPath('data.rejected', 0)
            ->assertJsonStructure(['data' => ['id', 'correlation_id'], 'meta', 'errors']);

        $this->assertSame(3, Notification::query()->count());
        $this->assertSame(1, NotificationBatch::query()->count());
        Queue::assertPushed(SendNotificationJob::class, 3);
    }

    public function test_it_rejects_batches_above_max_size(): void
    {
        config()->set('notifications.api.max_batch_size', 2);

        $payload = [
            'notifications' => [
                ['channel' => 'sms', 'recipient' => '+14155550100', 'content' => 'A'],
                ['channel' => 'sms', 'recipient' => '+14155550101', 'content' => 'B'],
                ['channel' => 'sms', 'recipient' => '+14155550102', 'content' => 'C'],
            ],
        ];

        $this->postJson('/api/v1/notifications/batch', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notifications']);
    }

    public function test_it_validates_each_notification_in_the_batch(): void
    {
        $this->postJson('/api/v1/notifications/batch', [
            'notifications' => [
                ['channel' => 'sms', 'recipient' => 'invalid', 'content' => 'A'],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['notifications.0.recipient']);
    }

    public function test_it_can_show_a_batch(): void
    {
        Queue::fake();

        $batchId = $this->postJson('/api/v1/notifications/batch', [
            'notifications' => [
                ['channel' => 'sms', 'recipient' => '+14155550100', 'content' => 'X'],
            ],
        ])->json('data.id');

        $this->getJson("/api/v1/batches/{$batchId}")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonStructure(['data' => ['id', 'status_breakdown']]);
    }
}
