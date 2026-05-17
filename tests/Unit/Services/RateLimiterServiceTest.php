<?php

namespace Tests\Unit\Services;

use App\Enums\NotificationChannel;
use App\Services\RateLimiterService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateLimiterServiceTest extends TestCase
{
    public function test_it_allows_calls_up_to_configured_limit(): void
    {
        config()->set('notifications.rate_limit.per_second', 3);
        Cache::flush();

        $limiter = new RateLimiterService(Cache::store());

        $this->assertTrue($limiter->attempt(NotificationChannel::Sms));
        $this->assertTrue($limiter->attempt(NotificationChannel::Sms));
        $this->assertTrue($limiter->attempt(NotificationChannel::Sms));
        $this->assertFalse($limiter->attempt(NotificationChannel::Sms));
    }

    public function test_separate_channels_have_independent_buckets(): void
    {
        config()->set('notifications.rate_limit.per_second', 1);
        Cache::flush();

        $limiter = new RateLimiterService(Cache::store());

        $this->assertTrue($limiter->attempt(NotificationChannel::Sms));
        $this->assertFalse($limiter->attempt(NotificationChannel::Sms));
        $this->assertTrue($limiter->attempt(NotificationChannel::Email));
        $this->assertTrue($limiter->attempt(NotificationChannel::Push));
    }
}
