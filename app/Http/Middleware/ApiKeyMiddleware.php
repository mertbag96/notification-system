<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('notifications.api.key', '');

        if ($expected === '') {
            return $next($request);
        }

        $provided = (string) $request->header('X-Api-Key', '');

        if (! hash_equals($expected, $provided)) {
            return new JsonResponse([
                'data' => null,
                'meta' => null,
                'errors' => [
                    ['code' => 'unauthorized', 'message' => 'Invalid or missing API key.'],
                ],
            ], 401);
        }

        return $next($request);
    }
}
