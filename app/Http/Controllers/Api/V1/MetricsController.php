<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\MetricsCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function __invoke(Request $request, MetricsCollector $metrics): JsonResponse
    {
        $queueDepth = Notification::query()
            ->whereIn('status', [NotificationStatus::Pending->value, NotificationStatus::Queued->value])
            ->selectRaw('priority, count(*) as total')
            ->groupBy('priority')
            ->pluck('total', 'priority')
            ->all();

        $depthByPriority = [];
        foreach (NotificationPriority::cases() as $priority) {
            $depthByPriority[$priority->value] = (int) ($queueDepth[$priority->value] ?? 0);
        }

        return response()->json([
            'data' => [
                'queue_depth' => $depthByPriority,
                'realtime' => $metrics->snapshot(),
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
            'errors' => [],
        ]);
    }
}
