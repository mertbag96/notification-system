<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Simple per-channel circuit breaker.
 *
 * Opens for `cooldown_seconds` once `failure_threshold` consecutive failures
 * have been observed. While open, callers should release jobs back to the
 * queue instead of hitting the provider.
 */
final class CircuitBreaker
{
    private const FAILURE_THRESHOLD = 50;

    private const COOLDOWN_SECONDS = 30;

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function isOpen(NotificationChannel $channel): bool
    {
        return (bool) $this->cache->get($this->openKey($channel), false);
    }

    public function recordSuccess(NotificationChannel $channel): void
    {
        $this->cache->forget($this->failureKey($channel));
        $this->cache->forget($this->openKey($channel));
    }

    public function recordFailure(NotificationChannel $channel): void
    {
        $key = $this->failureKey($channel);
        $this->cache->add($key, 0, now()->addMinutes(5));
        $failures = (int) $this->cache->increment($key);

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->cache->put($this->openKey($channel), true, now()->addSeconds(self::COOLDOWN_SECONDS));
        }
    }

    private function failureKey(NotificationChannel $channel): string
    {
        return "notifications:cb:failures:{$channel->value}";
    }

    private function openKey(NotificationChannel $channel): string
    {
        return "notifications:cb:open:{$channel->value}";
    }
}
