<?php

namespace App\Data;

final readonly class BatchData
{
    /**
     * @param  list<NotificationData>  $notifications
     */
    public function __construct(
        public array $notifications,
        public string $correlationId,
    ) {}

    /**
     * @param  array<int,array<string,mixed>>  $items
     */
    public static function fromArray(array $items, string $correlationId): self
    {
        $notifications = array_map(
            static fn (array $item) => NotificationData::fromArray($item, $correlationId),
            $items
        );

        return new self($notifications, $correlationId);
    }

    public function count(): int
    {
        return count($this->notifications);
    }
}
