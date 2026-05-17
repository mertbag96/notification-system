<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Sms = 'sms';
    case Email = 'email';
    case Push = 'push';

    /**
     * Recipient validation pattern for this channel.
     */
    public function recipientRule(): string
    {
        return match ($this) {
            self::Sms => 'regex:/^\+[1-9]\d{6,14}$/',
            self::Email => 'email:rfc',
            self::Push => 'string|min:8|max:512',
        };
    }

    public function contentLimit(): int
    {
        return (int) config('notifications.content_limits.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
