<?php

namespace App\Data;

final readonly class ProviderResponseData
{
    /**
     * @param  array<string,mixed>  $raw
     */
    public function __construct(
        public bool $accepted,
        public int $statusCode,
        public ?string $messageId,
        public ?string $providerStatus,
        public ?string $providerTimestamp,
        public array $raw,
        public int $latencyMs,
        public ?string $error = null,
        public ?int $retryAfterSeconds = null,
    ) {}

    public static function failure(
        int $statusCode,
        string $error,
        int $latencyMs,
        array $raw = [],
        ?int $retryAfterSeconds = null,
    ): self {
        return new self(
            accepted: false,
            statusCode: $statusCode,
            messageId: null,
            providerStatus: null,
            providerTimestamp: null,
            raw: $raw,
            latencyMs: $latencyMs,
            error: $error,
            retryAfterSeconds: $retryAfterSeconds,
        );
    }

    public function isRetryable(): bool
    {
        if ($this->accepted) {
            return false;
        }

        if ($this->statusCode === 0) {
            return true;
        }

        if (in_array($this->statusCode, [408, 425, 429], true)) {
            return true;
        }

        return $this->statusCode >= 500;
    }
}
