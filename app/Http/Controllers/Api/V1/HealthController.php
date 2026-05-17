<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'data' => [
                'status' => $healthy ? 'healthy' : 'degraded',
                'checks' => $checks,
            ],
            'meta' => [
                'app' => config('app.name'),
                'env' => config('app.env'),
                'time' => now()->toIso8601String(),
            ],
            'errors' => [],
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    private function checkCache(): array
    {
        try {
            $key = 'healthcheck:'.now()->timestamp;
            Cache::put($key, '1', 5);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return ['ok' => $ok];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    private function checkQueue(): array
    {
        try {
            Queue::size();

            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
