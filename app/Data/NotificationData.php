<?php

namespace App\Data;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Models\Notification;
use DateTimeImmutable;

final readonly class NotificationData
{
    /**
     * @param  array<string,mixed>|null  $payload
     */
    public function __construct(
        public NotificationChannel $channel,
        public NotificationPriority $priority,
        public string $recipient,
        public string $content,
        public ?array $payload = null,
        public ?DateTimeImmutable $scheduledAt = null,
        public ?string $templateId = null,
        public ?string $correlationId = null,
    ) {}

    public static function fromModel(Notification $notification): self
    {
        return new self(
            channel: $notification->channel,
            priority: $notification->priority,
            recipient: $notification->recipient,
            content: $notification->content,
            payload: $notification->payload,
            scheduledAt: $notification->scheduled_at?->toDateTimeImmutable(),
            templateId: $notification->template_id,
            correlationId: $notification->correlation_id,
        );
    }

    /**
     * @param  array<string,mixed>  $input
     */
    public static function fromArray(array $input, string $correlationId): self
    {
        return new self(
            channel: NotificationChannel::from($input['channel']),
            priority: NotificationPriority::from($input['priority'] ?? NotificationPriority::Normal->value),
            recipient: $input['recipient'],
            content: $input['content'],
            payload: $input['payload'] ?? null,
            scheduledAt: isset($input['scheduled_at'])
                ? new DateTimeImmutable($input['scheduled_at'])
                : null,
            templateId: $input['template_id'] ?? null,
            correlationId: $correlationId,
        );
    }
}
