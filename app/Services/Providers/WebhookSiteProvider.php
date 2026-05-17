<?php

namespace App\Services\Providers;

use App\Data\NotificationData;
use App\Data\ProviderResponseData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WebhookSiteProvider implements NotificationProvider
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function send(NotificationData $data): ProviderResponseData
    {
        $url = (string) config('services.webhook_site.url');
        $timeout = (int) config('services.webhook_site.timeout', 5);

        if ($url === '') {
            return ProviderResponseData::failure(
                statusCode: 0,
                error: 'WEBHOOK_SITE_URL is not configured',
                latencyMs: 0,
            );
        }

        $startedAt = hrtime(true);

        try {
            $response = $this->http
                ->timeout($timeout)
                ->connectTimeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-Correlation-Id' => (string) $data->correlationId,
                ])
                ->post($url, [
                    'to' => $data->recipient,
                    'channel' => $data->channel->value,
                    'content' => $data->content,
                ]);

            $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $body = $response->json() ?? [];

            if ($response->status() === 202 || $this->isAcceptedSuccessResponse($response, $body)) {
                return new ProviderResponseData(
                    accepted: true,
                    statusCode: $response->status(),
                    messageId: is_string($body['messageId'] ?? null) ? $body['messageId'] : null,
                    providerStatus: is_string($body['status'] ?? null) ? $body['status'] : null,
                    providerTimestamp: is_string($body['timestamp'] ?? null) ? $body['timestamp'] : null,
                    raw: is_array($body) ? $body : [],
                    latencyMs: $latency,
                );
            }

            return ProviderResponseData::failure(
                statusCode: $response->status(),
                error: 'Provider returned non-success status',
                latencyMs: $latency,
                raw: is_array($body) ? $body : [],
                retryAfterSeconds: self::parseRetryAfterHeader($response->header('Retry-After')),
            );
        } catch (ConnectionException $exception) {
            $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            return ProviderResponseData::failure(
                statusCode: 0,
                error: 'Connection error: '.$exception->getMessage(),
                latencyMs: $latency,
            );
        } catch (Throwable $exception) {
            $latency = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            Log::channel('notifications')->error('provider.exception', [
                'message' => $exception->getMessage(),
                'correlation_id' => $data->correlationId,
            ]);

            return ProviderResponseData::failure(
                statusCode: 0,
                error: $exception->getMessage(),
                latencyMs: $latency,
            );
        }
    }

    /**
     * webhook.site occasionally returns HTTP 200 with a configurable JSON body — treat it as acceptance
     * when it matches the expected assessment shape so local demos still work beyond strict 202.
     *
     * @param  array<string,mixed>  $body
     */
    private function isAcceptedSuccessResponse(Response $response, array $body): bool
    {
        if (! $response->successful()) {
            return false;
        }

        if (! is_string($body['messageId'] ?? null)) {
            return false;
        }

        $status = $body['status'] ?? null;

        return $status === 'accepted' || $status === null;
    }

    /**
     * Parse Retry-After (seconds integer or RFC 7231 HTTP-date).
     */
    private static function parseRetryAfterHeader(?string $header): ?int
    {
        if ($header === null) {
            return null;
        }

        $trimmed = trim($header);

        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return max(1, (int) $trimmed);
        }

        try {
            /** @var Carbon $retryAt */
            $retryAt = Carbon::parse($trimmed)->utc();
            $seconds = (int) ($retryAt->getTimestamp() - Carbon::now('UTC')->getTimestamp());

            return max(1, $seconds);
        } catch (Throwable) {
            return null;
        }
    }
}
