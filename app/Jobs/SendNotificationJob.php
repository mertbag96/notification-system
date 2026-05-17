<?php

namespace App\Jobs;

use App\Data\NotificationData;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationAttempt;
use App\Services\CircuitBreaker;
use App\Services\MetricsCollector;
use App\Services\Providers\NotificationProvider;
use App\Services\RateLimiterService;
use App\Support\CorrelationId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 30;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return (array) config('notifications.retry.backoff_seconds', [2, 5, 15, 60, 300]);
    }

    public function __construct(
        public readonly string $notificationId,
    ) {}

    public function handle(
        NotificationProvider $provider,
        RateLimiterService $rateLimiter,
        CircuitBreaker $circuit,
        MetricsCollector $metrics,
    ): void {
        $notification = Notification::query()->find($this->notificationId);

        if ($notification === null) {
            Log::channel('notifications')->warning('job.missing_notification', [
                'notification_id' => $this->notificationId,
            ]);

            return;
        }

        CorrelationId::set($notification->correlation_id);

        if ($notification->status === NotificationStatus::Cancelled) {
            Log::channel('notifications')->info('job.skipped_cancelled', [
                'notification_id' => $notification->id,
                'correlation_id' => $notification->correlation_id,
            ]);

            return;
        }

        if ($circuit->isOpen($notification->channel)) {
            Log::channel('notifications')->warning('job.circuit_open', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'correlation_id' => $notification->correlation_id,
            ]);
            $this->release(5);

            return;
        }

        if (! $rateLimiter->attempt($notification->channel)) {
            Log::channel('notifications')->info('job.rate_limited', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'correlation_id' => $notification->correlation_id,
            ]);
            $this->release(1);

            return;
        }

        $attemptNumber = (int) NotificationAttempt::query()
            ->where('notification_id', $notification->id)
            ->max('attempt_number') + 1;

        $notification->update([
            'status' => NotificationStatus::Processing,
            'dispatched_at' => $notification->dispatched_at ?? now(),
        ]);

        $response = $provider->send(NotificationData::fromModel($notification));

        NotificationAttempt::query()->create([
            'notification_id' => $notification->id,
            'attempt_number' => $attemptNumber,
            'status' => $response->accepted ? NotificationStatus::Sent : NotificationStatus::Failed,
            'response_status' => $response->statusCode,
            'provider_response' => $response->raw,
            'latency_ms' => $response->latencyMs,
            'error' => $response->error,
        ]);

        $notification->update(['attempts' => $attemptNumber]);

        if ($response->accepted) {
            $notification->update([
                'status' => NotificationStatus::Sent,
                'provider_message_id' => $response->messageId,
                'delivered_at' => now(),
                'last_error' => null,
            ]);

            $circuit->recordSuccess($notification->channel);
            $metrics->recordOutcome($notification->channel, NotificationStatus::Sent, $response->latencyMs);

            Log::channel('notifications')->info('job.sent', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'provider_message_id' => $response->messageId,
                'latency_ms' => $response->latencyMs,
                'correlation_id' => $notification->correlation_id,
            ]);

            return;
        }

        if ($response->statusCode === 429) {
            $fourTwoNineRounds = NotificationAttempt::query()
                ->where('notification_id', $notification->id)
                ->where('response_status', 429)
                ->count();

            $maxRounds429 = max(3, (int) config(
                'notifications.provider.http_429_max_rounds_per_notification',
                50,
            ));

            if ($fourTwoNineRounds >= $maxRounds429) {
                $circuit->recordFailure($notification->channel);

                Log::channel('notifications')->warning('job.provider_rate_limit_cap', [
                    'notification_id' => $notification->id,
                    'attempts_http_429' => $fourTwoNineRounds,
                    'correlation_id' => $notification->correlation_id,
                ]);

                $notification->update([
                    'status' => NotificationStatus::Failed,
                    'last_error' => 'Provider kept returning HTTP 429 (quota / rate limiting). Obtain a fresh webhook inbox URL at webhook.site or try again later.',
                ]);

                $metrics->recordOutcome($notification->channel, NotificationStatus::Failed, $response->latencyMs);

                return;
            }

            $defaultDelay = max(45, (int) config(
                'notifications.provider.http_429_default_retry_seconds',
                120,
            ));
            $cap = max($defaultDelay, (int) config(
                'notifications.provider.http_429_retry_seconds_cap',
                3600,
            ));

            $delay = $response->retryAfterSeconds !== null
                ? max(45, min($cap, $response->retryAfterSeconds))
                : $defaultDelay;

            $notification->update([
                'status' => NotificationStatus::Queued,
                'last_error' => sprintf(
                    'Provider temporarily rate limited (HTTP 429); retry scheduled in ~%ds.',
                    $delay,
                ),
            ]);

            Log::channel('notifications')->notice('job.provider_throttled_release', [
                'notification_id' => $notification->id,
                'channel' => $notification->channel->value,
                'delay_seconds' => $delay,
                'http_429_round' => $fourTwoNineRounds,
                'correlation_id' => $notification->correlation_id,
            ]);

            // Re-dispatch as a fresh delayed job instead of $this->release($delay).
            // release() counts toward Laravel's $tries cap, which would dead-letter
            // the notification after 5 rounds and defeat the per-notification 429
            // round budget (`notifications.provider.http_429_max_rounds_per_notification`).
            self::dispatch($notification->id)
                ->onQueue($notification->priority->queueName())
                ->delay($delay);

            return;
        }

        $circuit->recordFailure($notification->channel);
        $metrics->recordOutcome($notification->channel, NotificationStatus::Failed, $response->latencyMs);

        Log::channel('notifications')->warning('job.attempt_failed', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel->value,
            'status_code' => $response->statusCode,
            'error' => $response->error,
            'attempts' => $attemptNumber,
            'correlation_id' => $notification->correlation_id,
        ]);

        if ($response->isRetryable() && $attemptNumber < $this->tries) {
            $notification->update([
                'status' => NotificationStatus::Queued,
                'last_error' => $response->error,
            ]);

            throw new \RuntimeException(
                "Provider error (status {$response->statusCode}): {$response->error}"
            );
        }

        $notification->update([
            'status' => NotificationStatus::Failed,
            'last_error' => $response->error,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $notification = Notification::query()->find($this->notificationId);

        if ($notification === null) {
            return;
        }

        $notification->update([
            'status' => NotificationStatus::DeadLettered,
            'last_error' => $e->getMessage(),
        ]);

        Log::channel('notifications')->error('job.dead_lettered', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel->value,
            'error' => $e->getMessage(),
            'correlation_id' => $notification->correlation_id,
        ]);
    }
}
