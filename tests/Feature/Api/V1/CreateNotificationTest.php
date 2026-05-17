<?php

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CreateNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_notification_and_dispatches_the_send_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'priority' => 'high',
            'recipient' => '+14155550100',
            'content' => 'Welcome aboard!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.channel', 'sms')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', NotificationStatus::Queued->value)
            ->assertJsonStructure(['data' => ['id', 'correlation_id'], 'meta', 'errors']);

        $this->assertDatabaseCount('notifications', 1);

        Queue::assertPushedOn('notifications-high', SendNotificationJob::class);
    }

    public function test_it_returns_422_for_invalid_recipient(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'recipient' => 'not-a-phone',
            'content' => 'Hello',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recipient']);
    }

    public function test_it_rejects_content_exceeding_channel_limit(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'recipient' => '+14155550100',
            'content' => str_repeat('a', 161),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_it_defaults_priority_to_normal(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', [
            'channel' => 'email',
            'recipient' => 'jane@example.com',
            'content' => 'Hello',
        ])->assertCreated();

        $this->assertSame(
            NotificationPriority::Normal,
            Notification::query()->first()->priority,
        );
    }

    public function test_scheduled_notifications_are_persisted_as_pending(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', [
            'channel' => 'sms',
            'recipient' => '+14155550100',
            'content' => 'Later',
            'scheduled_at' => now()->addMinutes(10)->toIso8601String(),
        ])->assertCreated();

        $notification = Notification::query()->firstOrFail();
        $this->assertSame(NotificationStatus::Pending, $notification->status);
        Queue::assertNothingPushed();
    }

    public function test_it_returns_correlation_id_header(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/notifications', [
            'channel' => 'push',
            'recipient' => str_repeat('a', 32),
            'content' => 'Push payload',
        ]);

        $response->assertCreated();
        $this->assertNotEmpty($response->headers->get('X-Correlation-Id'));
    }

    public function test_it_honours_supplied_correlation_id_header(): void
    {
        Queue::fake();

        $correlation = 'fixed-correlation-id-123';

        $response = $this->withHeader('X-Correlation-Id', $correlation)
            ->postJson('/api/v1/notifications', [
                'channel' => 'sms',
                'recipient' => '+14155550100',
                'content' => 'Hi',
            ]);

        $response->assertCreated();
        $this->assertSame($correlation, $response->headers->get('X-Correlation-Id'));
        $this->assertSame($correlation, Notification::query()->first()->correlation_id);
    }

    public function test_it_validates_channel_enum(): void
    {
        $this->postJson('/api/v1/notifications', [
            'channel' => 'fax',
            'recipient' => '+14155550100',
            'content' => 'Hi',
        ])->assertStatus(422)->assertJsonValidationErrors(['channel']);
    }

    public function test_email_channel_validates_email_recipient(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/notifications', [
            'channel' => NotificationChannel::Email->value,
            'recipient' => 'not-an-email',
            'content' => 'Hello',
        ])->assertStatus(422)->assertJsonValidationErrors(['recipient']);
    }
}
