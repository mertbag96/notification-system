<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        $channel = fake()->randomElement(NotificationChannel::cases());

        return [
            'channel' => $channel,
            'priority' => NotificationPriority::Normal,
            'status' => NotificationStatus::Pending,
            'recipient' => match ($channel) {
                NotificationChannel::Sms => '+1'.fake()->numerify('##########'),
                NotificationChannel::Email => fake()->safeEmail(),
                NotificationChannel::Push => Str::random(64),
            },
            'content' => fake()->sentence(),
            'payload' => null,
            'attempts' => 0,
            'correlation_id' => (string) Str::uuid(),
        ];
    }

    public function ofChannel(NotificationChannel $channel): static
    {
        return $this->state(fn () => [
            'channel' => $channel,
            'recipient' => match ($channel) {
                NotificationChannel::Sms => '+1'.fake()->numerify('##########'),
                NotificationChannel::Email => fake()->safeEmail(),
                NotificationChannel::Push => Str::random(64),
            },
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn () => ['priority' => NotificationPriority::High]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn () => ['priority' => NotificationPriority::Low]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => NotificationStatus::Pending]);
    }

    public function queued(): static
    {
        return $this->state(fn () => ['status' => NotificationStatus::Queued]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Sent,
            'provider_message_id' => (string) Str::uuid(),
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Failed,
            'last_error' => 'Provider returned 500',
            'attempts' => 5,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => [
            'scheduled_at' => $at ?? now()->addMinutes(5),
        ]);
    }
}
