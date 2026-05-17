<?php

namespace App\Support;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

final class CorrelationId
{
    public const CONTEXT_KEY = 'correlation_id';

    public const HEADER = 'X-Correlation-Id';

    public static function set(string $id): void
    {
        Context::add(self::CONTEXT_KEY, $id);
    }

    public static function current(): string
    {
        $value = Context::get(self::CONTEXT_KEY);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fresh = self::generate();
        self::set($fresh);

        return $fresh;
    }

    public static function generate(): string
    {
        return (string) Str::uuid();
    }
}
