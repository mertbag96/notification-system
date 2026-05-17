<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $header = (string) config('notifications.idempotency.header', 'Idempotency-Key');
        $key = (string) $request->header($header, '');

        if ($key === '') {
            return $next($request);
        }

        $hash = hash('sha256', $request->getContent() ?: '');

        $existing = IdempotencyKey::query()->find($key);

        if ($existing !== null && ! $existing->isExpired()) {
            if ($existing->request_hash !== $hash) {
                return new JsonResponse([
                    'data' => null,
                    'meta' => null,
                    'errors' => [
                        ['code' => 'idempotency_conflict', 'message' => 'Idempotency key reused with a different payload.'],
                    ],
                ], 409);
            }

            return new JsonResponse(
                $existing->response,
                $existing->status_code,
                ['Idempotent-Replay' => 'true']
            );
        }

        /** @var JsonResponse $response */
        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            try {
                IdempotencyKey::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'request_hash' => $hash,
                        'status_code' => $response->getStatusCode(),
                        'response' => $response->getData(true),
                        'expires_at' => now()->addHours((int) config('notifications.idempotency.ttl_hours', 24)),
                    ],
                );
            } catch (QueryException) {
                // Race condition under concurrent identical requests is harmless; ignore.
            }
        }

        return $response;
    }
}
