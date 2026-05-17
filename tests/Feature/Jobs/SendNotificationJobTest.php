<?php

namespace Tests\Feature\Jobs;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\CircuitBreaker;
use App\Services\MetricsCollector;
use App\Services\Providers\NotificationProvider;
use App\Services\RateLimiterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.webhook_site.url', 'https://webhook.site/test');
    }

    public function test_it_marks_notification_as_sent_when_provider_returns_202(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response([
                'messageId' => 'msg-uuid-1',
                'status' => 'accepted',
                'timestamp' => now()->toIso8601String(),
            ], 202),
        ]);

        $notification = Notification::factory()->queued()->create();

        (new SendNotificationJob($notification->id))->handle(
            app(NotificationProvider::class),
            app(RateLimiterService::class),
            app(CircuitBreaker::class),
            app(MetricsCollector::class),
        );

        $notification->refresh();
        $this->assertSame(NotificationStatus::Sent, $notification->status);
        $this->assertSame('msg-uuid-1', $notification->provider_message_id);
        $this->assertSame(1, $notification->attemptsLog()->count());
    }

    public function test_it_throws_to_trigger_retry_on_500_responses(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(['error' => 'oops'], 500),
        ]);

        $notification = Notification::factory()->queued()->create();

        $this->expectException(\RuntimeException::class);

        (new SendNotificationJob($notification->id))->handle(
            app(NotificationProvider::class),
            app(RateLimiterService::class),
            app(CircuitBreaker::class),
            app(MetricsCollector::class),
        );
    }

    public function test_it_marks_notification_as_failed_for_4xx_non_retryable_response(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(['error' => 'bad'], 400),
        ]);

        $notification = Notification::factory()->queued()->create();

        (new SendNotificationJob($notification->id))->handle(
            app(NotificationProvider::class),
            app(RateLimiterService::class),
            app(CircuitBreaker::class),
            app(MetricsCollector::class),
        );

        $this->assertSame(NotificationStatus::Failed, $notification->fresh()->status);
    }

    public function test_it_skips_cancelled_notifications(): void
    {
        $notification = Notification::factory()->cancelled()->create();

        (new SendNotificationJob($notification->id))->handle(
            app(NotificationProvider::class),
            app(RateLimiterService::class),
            app(CircuitBreaker::class),
            app(MetricsCollector::class),
        );

        $this->assertSame(NotificationStatus::Cancelled, $notification->fresh()->status);
        $this->assertSame(0, $notification->attemptsLog()->count());
        Http::assertNothingSent();
    }

    public function test_it_requeues_on_http_429_without_runtime_exception(): void
    {
        Http::fake([
            'webhook.site/*' => Http::response(
                ['error' => 'Too Many Requests'],
                429,
                ['Retry-After' => '90'],
            ),
        ]);

        config()->set('notifications.provider.http_429_max_rounds_per_notification', 20);

        $notification = Notification::factory()->queued()->create();

        (new SendNotificationJob($notification->id))->handle(
            app(NotificationProvider::class),
            app(RateLimiterService::class),
            app(CircuitBreaker::class),
            app(MetricsCollector::class),
        );

        $notification->refresh();
        $this->assertSame(NotificationStatus::Queued, $notification->status);
        $this->assertSame(429, $notification->attemptsLog()->first()->response_status);
        $this->assertStringContainsString('HTTP 429', (string) $notification->last_error);
        Http::assertSentCount(1);
    }

    public function test_http_429_redispatches_fresh_job_instead_of_consuming_tries(): void
    {
        Queue::fake();

        Http::fake([
            'webhook.site/*' => Http::response(
                ['error' => 'Too Many Requests'],
                429,
                ['Retry-After' => '90'],
            ),
        ]);

        config()->set('notifications.provider.http_429_max_rounds_per_notification', 20);
        config()->set('notifications.provider.http_429_default_retry_seconds', 120);

        $notification = Notification::factory()->queued()->create();

        (new SendNotificationJob($notification->id))->handle(
            app(NotificationProvider::class),
            app(RateLimiterService::class),
            app(CircuitBreaker::class),
            app(MetricsCollector::class),
        );

        Queue::assertPushed(
            SendNotificationJob::class,
            function (SendNotificationJob $job) use ($notification): bool {
                return $job->notificationId === $notification->id
                    && $job->queue === $notification->priority->queueName()
                    && $job->delay >= 90;
            },
        );
    }
}
