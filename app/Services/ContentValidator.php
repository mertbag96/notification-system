<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Exceptions\InvalidNotificationContentException;

final class ContentValidator
{
    public function ensureValid(NotificationChannel $channel, string $recipient, string $content): void
    {
        if (trim($content) === '') {
            throw new InvalidNotificationContentException('Content cannot be empty.');
        }

        $limit = $channel->contentLimit();

        if (mb_strlen($content) > $limit) {
            throw new InvalidNotificationContentException(
                "Content exceeds the maximum length of {$limit} characters for channel {$channel->value}."
            );
        }

        $this->ensureRecipientFormat($channel, $recipient);
    }

    private function ensureRecipientFormat(NotificationChannel $channel, string $recipient): void
    {
        $valid = match ($channel) {
            NotificationChannel::Sms => preg_match('/^\+[1-9]\d{6,14}$/', $recipient) === 1,
            NotificationChannel::Email => filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false,
            NotificationChannel::Push => mb_strlen($recipient) >= 8 && mb_strlen($recipient) <= 512,
        };

        if (! $valid) {
            throw new InvalidNotificationContentException(
                "Recipient is invalid for channel {$channel->value}."
            );
        }
    }
}
