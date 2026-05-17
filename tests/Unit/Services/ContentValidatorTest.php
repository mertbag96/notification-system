<?php

namespace Tests\Unit\Services;

use App\Enums\NotificationChannel;
use App\Exceptions\InvalidNotificationContentException;
use App\Services\ContentValidator;
use Tests\TestCase;

class ContentValidatorTest extends TestCase
{
    private ContentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ContentValidator;
    }

    public function test_it_passes_valid_sms_payload(): void
    {
        $this->validator->ensureValid(NotificationChannel::Sms, '+14155550100', 'Hi');
        $this->expectNotToPerformAssertions();
    }

    public function test_it_rejects_empty_content(): void
    {
        $this->expectException(InvalidNotificationContentException::class);
        $this->validator->ensureValid(NotificationChannel::Sms, '+14155550100', '   ');
    }

    public function test_it_rejects_content_over_sms_limit(): void
    {
        $this->expectException(InvalidNotificationContentException::class);
        $this->validator->ensureValid(NotificationChannel::Sms, '+14155550100', str_repeat('a', 161));
    }

    public function test_it_rejects_invalid_email_recipient(): void
    {
        $this->expectException(InvalidNotificationContentException::class);
        $this->validator->ensureValid(NotificationChannel::Email, 'no-at-sign', 'Hi');
    }

    public function test_it_rejects_too_short_push_token(): void
    {
        $this->expectException(InvalidNotificationContentException::class);
        $this->validator->ensureValid(NotificationChannel::Push, 'short', 'Hi');
    }
}
