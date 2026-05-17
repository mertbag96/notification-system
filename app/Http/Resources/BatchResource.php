<?php

namespace App\Http\Resources;

use App\Models\NotificationBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NotificationBatch $resource
 */
class BatchResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $statusBreakdown = $this->resource
            ->notifications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        return [
            'id' => $this->resource->id,
            'correlation_id' => $this->resource->correlation_id,
            'total' => $this->resource->total,
            'accepted' => $this->resource->accepted,
            'rejected' => $this->resource->rejected,
            'status_breakdown' => $statusBreakdown,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }
}
