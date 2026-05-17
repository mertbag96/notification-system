<?php

namespace Tests\Feature\Api\V1;

use App\Models\Notification;
use App\Models\NotificationAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_a_notification_with_attempts_log(): void
    {
        $notification = Notification::factory()->sent()->create();
        NotificationAttempt::factory()->count(2)->create([
            'notification_id' => $notification->id,
        ]);

        $this->getJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonCount(2, 'data.attempts_log');
    }

    public function test_it_returns_404_for_missing_notification(): void
    {
        $this->getJson('/api/v1/notifications/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }
}
