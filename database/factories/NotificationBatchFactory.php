<?php

namespace Database\Factories;

use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationBatch>
 */
class NotificationBatchFactory extends Factory
{
    protected $model = NotificationBatch::class;

    public function definition(): array
    {
        return [
            'correlation_id' => (string) Str::uuid(),
            'total' => 0,
            'accepted' => 0,
            'rejected' => 0,
        ];
    }
}
