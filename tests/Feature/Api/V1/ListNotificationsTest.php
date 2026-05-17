<?php

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_notifications_with_pagination(): void
    {
        Notification::factory()->count(30)->create();

        $this->getJson('/api/v1/notifications?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_it_filters_by_channel(): void
    {
        Notification::factory()->count(3)->ofChannel(NotificationChannel::Sms)->create();
        Notification::factory()->count(5)->ofChannel(NotificationChannel::Email)->create();

        $this->getJson('/api/v1/notifications?channel=email')
            ->assertOk()
            ->assertJsonPath('meta.total', 5);
    }

    public function test_it_filters_by_status(): void
    {
        Notification::factory()->sent()->count(2)->create();
        Notification::factory()->failed()->count(4)->create();

        $this->getJson('/api/v1/notifications?status=failed')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);
    }

    public function test_it_filters_by_date_range(): void
    {
        Notification::factory()->count(2)->create(['created_at' => now()->subDays(5)]);
        Notification::factory()->count(3)->create(['created_at' => now()->subDay()]);

        $from = now()->subDays(2)->toDateString();
        $to = now()->toDateString();

        $this->getJson("/api/v1/notifications?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }
}
