<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class MetricsCollector
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function recordOutcome(
        NotificationChannel $channel,
        NotificationStatus $status,
        int $latencyMs,
    ): void {
        $minute = $this->currentMinute();

        $this->increment($this->counterKey('total', $channel, $minute));
        $this->increment($this->counterKey($status->value, $channel, $minute));

        $samplesKey = $this->latencyKey($channel, $minute);
        $samples = (array) $this->cache->get($samplesKey, []);
        $samples[] = $latencyMs;

        if (count($samples) > 500) {
            $samples = array_slice($samples, -500);
        }

        $this->cache->put($samplesKey, $samples, now()->addSeconds(600));
    }

    /**
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        $window = (int) config('notifications.metrics.window_seconds', 300);
        $minutes = max(1, (int) ceil($window / 60));
        $now = $this->currentMinute();
        $minuteKeys = [];

        for ($i = 0; $i < $minutes; $i++) {
            $minuteKeys[] = $now - $i * 60;
        }

        $byChannel = [];
        $totalSuccess = 0;
        $totalFailure = 0;
        $allLatencies = [];

        foreach (NotificationChannel::cases() as $channel) {
            $success = 0;
            $failure = 0;
            $samples = [];

            foreach ($minuteKeys as $bucket) {
                $success += (int) $this->cache->get(
                    $this->counterKey(NotificationStatus::Sent->value, $channel, $bucket),
                    0,
                );
                $failure += (int) $this->cache->get(
                    $this->counterKey(NotificationStatus::Failed->value, $channel, $bucket),
                    0,
                );
                $samples = array_merge(
                    $samples,
                    (array) $this->cache->get($this->latencyKey($channel, $bucket), []),
                );
            }

            $byChannel[$channel->value] = [
                'success' => $success,
                'failure' => $failure,
                'latency_ms' => $this->percentiles($samples),
            ];

            $totalSuccess += $success;
            $totalFailure += $failure;
            $allLatencies = array_merge($allLatencies, $samples);
        }

        $total = $totalSuccess + $totalFailure;

        return [
            'window_seconds' => $window,
            'totals' => [
                'success' => $totalSuccess,
                'failure' => $totalFailure,
                'success_rate' => $total > 0 ? round($totalSuccess / $total, 4) : null,
                'failure_rate' => $total > 0 ? round($totalFailure / $total, 4) : null,
            ],
            'latency_ms' => $this->percentiles($allLatencies),
            'by_channel' => $byChannel,
        ];
    }

    private function currentMinute(): int
    {
        return (int) floor(time() / 60) * 60;
    }

    private function counterKey(string $segment, NotificationChannel $channel, int $minute): string
    {
        return "notifications:metrics:{$channel->value}:{$segment}:{$minute}";
    }

    private function latencyKey(NotificationChannel $channel, int $minute): string
    {
        return "notifications:metrics:{$channel->value}:latency:{$minute}";
    }

    private function increment(string $key): void
    {
        $this->cache->add($key, 0, now()->addSeconds(600));
        $this->cache->increment($key);
    }

    /**
     * @param  list<int>  $samples
     * @return array<string,int|null>
     */
    private function percentiles(array $samples): array
    {
        if ($samples === []) {
            return ['p50' => null, 'p95' => null, 'p99' => null];
        }

        sort($samples);
        $count = count($samples);

        return [
            'p50' => $samples[(int) floor(($count - 1) * 0.50)],
            'p95' => $samples[(int) floor(($count - 1) * 0.95)],
            'p99' => $samples[(int) floor(($count - 1) * 0.99)],
        ];
    }
}
