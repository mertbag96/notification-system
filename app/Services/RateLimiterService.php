<?php

namespace App\Services;

use App\Enums\NotificationChannel;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Token-bucket style rate limiter using the cache store.
 *
 * Caps provider calls per channel per second. Falls back gracefully when the
 * cache driver doesn't support atomic increments by short-circuiting to allow.
 */
final class RateLimiterService
{
    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function attempt(NotificationChannel $channel): bool
    {
        $limit = (int) config('notifications.rate_limit.per_second', 100);

        if ($limit <= 0) {
            return true;
        }

        $bucketKey = $this->bucketKey($channel);
        $expiresAt = now()->addSeconds(2);

        $this->cache->add($bucketKey, 0, $expiresAt);

        $current = (int) $this->cache->increment($bucketKey);

        if ($current === 1) {
            $this->cache->put($bucketKey, 1, $expiresAt);
        }

        return $current <= $limit;
    }

    public function bucketKey(NotificationChannel $channel): string
    {
        return sprintf('notifications:rl:%s:%d', $channel->value, time());
    }

    public function reset(NotificationChannel $channel): void
    {
        Cache::forget($this->bucketKey($channel));
    }
}
