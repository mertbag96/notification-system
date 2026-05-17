<?php

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_cancels_a_pending_notification(): void
    {
        $notification = Notification::factory()->pending()->create();

        $this->postJson("/api/v1/notifications/{$notification->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', NotificationStatus::Cancelled->value);

        $this->assertSame(NotificationStatus::Cancelled, $notification->fresh()->status);
    }

    public function test_it_cancels_a_queued_notification(): void
    {
        $notification = Notification::factory()->queued()->create();

        $this->postJson("/api/v1/notifications/{$notification->id}/cancel")
            ->assertOk();
    }

    public function test_it_returns_409_when_cancelling_a_sent_notification(): void
    {
        $notification = Notification::factory()->sent()->create();

        $this->postJson("/api/v1/notifications/{$notification->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'not_cancellable');
    }

    public function test_it_returns_404_when_notification_is_missing(): void
    {
        $this->postJson('/api/v1/notifications/00000000-0000-0000-0000-000000000000/cancel')
            ->assertNotFound();
    }
}
