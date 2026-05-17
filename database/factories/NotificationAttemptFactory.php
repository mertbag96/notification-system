<?php

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationAttempt>
 */
class NotificationAttemptFactory extends Factory
{
    protected $model = NotificationAttempt::class;

    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'attempt_number' => 1,
            'status' => NotificationStatus::Sent,
            'response_status' => 202,
            'provider_response' => ['messageId' => fake()->uuid(), 'status' => 'accepted'],
            'latency_ms' => fake()->numberBetween(50, 800),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::Failed,
            'response_status' => 500,
            'error' => 'Upstream error',
        ]);
    }
}
