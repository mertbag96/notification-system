<?php

namespace Tests\Unit\Services\Providers;

use App\Data\NotificationData;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Services\Providers\WebhookSiteProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSiteProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.webhook_site.url', 'https://webhook.site/test-uuid');
    }

    public function test_it_accepts_http_200_with_acceptance_shaped_payload(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response([
                'messageId' => 'via-200',
                'status' => 'accepted',
                'timestamp' => '2026-01-01T00:00:00Z',
            ], 200),
        ]);

        $provider = new WebhookSiteProvider(app(Factory::class));

        $response = $provider->send(new NotificationData(
            channel: NotificationChannel::Sms,
            priority: NotificationPriority::Normal,
            recipient: '+14155550100',
            content: 'Hello',
        ));

        $this->assertTrue($response->accepted);
        $this->assertSame(200, $response->statusCode);
        $this->assertSame('via-200', $response->messageId);
    }

    public function test_it_returns_accepted_for_202(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response([
                'messageId' => 'msg-1',
                'status' => 'accepted',
                'timestamp' => '2026-01-01T00:00:00Z',
            ], 202),
        ]);

        $provider = new WebhookSiteProvider(app(Factory::class));

        $response = $provider->send(new NotificationData(
            channel: NotificationChannel::Sms,
            priority: NotificationPriority::Normal,
            recipient: '+14155550100',
            content: 'Hello',
        ));

        $this->assertTrue($response->accepted);
        $this->assertSame(202, $response->statusCode);
        $this->assertSame('msg-1', $response->messageId);
        $this->assertGreaterThanOrEqual(0, $response->latencyMs);
    }

    public function test_it_returns_failure_for_500(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(['error' => 'boom'], 500),
        ]);

        $provider = new WebhookSiteProvider(app(Factory::class));

        $response = $provider->send(new NotificationData(
            channel: NotificationChannel::Email,
            priority: NotificationPriority::Normal,
            recipient: 'a@b.com',
            content: 'Hi',
        ));

        $this->assertFalse($response->accepted);
        $this->assertSame(500, $response->statusCode);
        $this->assertTrue($response->isRetryable());
    }

    public function test_4xx_responses_are_not_retryable(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(['error' => 'bad'], 400),
        ]);

        $provider = new WebhookSiteProvider(app(Factory::class));

        $response = $provider->send(new NotificationData(
            channel: NotificationChannel::Push,
            priority: NotificationPriority::Low,
            recipient: str_repeat('x', 32),
            content: 'Hi',
        ));

        $this->assertFalse($response->accepted);
        $this->assertFalse($response->isRetryable());
    }

    public function test_429_is_retryable(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(['error' => 'too many'], 429),
        ]);

        $provider = new WebhookSiteProvider(app(Factory::class));

        $response = $provider->send(new NotificationData(
            channel: NotificationChannel::Sms,
            priority: NotificationPriority::High,
            recipient: '+14155550100',
            content: 'Hi',
        ));

        $this->assertTrue($response->isRetryable());
    }
}
