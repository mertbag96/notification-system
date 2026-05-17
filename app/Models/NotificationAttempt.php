<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Database\Factories\NotificationAttemptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $notification_id
 * @property int $attempt_number
 * @property NotificationStatus $status
 * @property int|null $response_status
 * @property array<string,mixed>|null $provider_response
 * @property int|null $latency_ms
 * @property string|null $error
 */
class NotificationAttempt extends Model
{
    /** @use HasFactory<NotificationAttemptFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'notification_id',
        'attempt_number',
        'status',
        'response_status',
        'provider_response',
        'latency_ms',
        'error',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'provider_response' => 'array',
            'attempt_number' => 'integer',
            'response_status' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
