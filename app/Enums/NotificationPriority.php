<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public function queueName(): string
    {
        return (string) config('notifications.queues.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
