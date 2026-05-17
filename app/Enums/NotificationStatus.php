<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case DeadLettered = 'dead_lettered';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Sent, self::Failed, self::Cancelled, self::DeadLettered], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Queued], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
