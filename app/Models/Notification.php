<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $batch_id
 * @property string|null $template_id
 * @property NotificationChannel $channel
 * @property NotificationPriority $priority
 * @property NotificationStatus $status
 * @property string $recipient
 * @property string $content
 * @property array<string,mixed>|null $payload
 * @property string|null $provider_message_id
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property string $correlation_id
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'batch_id',
        'template_id',
        'channel',
        'priority',
        'status',
        'recipient',
        'content',
        'payload',
        'provider_message_id',
        'attempts',
        'last_error',
        'scheduled_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'correlation_id',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'priority' => NotificationPriority::class,
            'status' => NotificationStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    /**
     * @return HasMany<NotificationAttempt>
     */
    public function attemptsLog(): HasMany
    {
        return $this->hasMany(NotificationAttempt::class);
    }

    public function scopeOfStatus(Builder $query, NotificationStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof NotificationStatus ? $status->value : $status);
    }

    public function scopeOfChannel(Builder $query, NotificationChannel|string $channel): Builder
    {
        return $query->where('channel', $channel instanceof NotificationChannel ? $channel->value : $channel);
    }

    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from !== null) {
            $query->where('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }

    public function scopeDueForDispatch(Builder $query): Builder
    {
        return $query->where('status', NotificationStatus::Pending->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }
}
