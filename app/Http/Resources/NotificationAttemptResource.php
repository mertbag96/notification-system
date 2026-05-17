<?php

namespace App\Http\Resources;

use App\Models\NotificationAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NotificationAttempt $resource
 */
class NotificationAttemptResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'attempt_number' => $this->resource->attempt_number,
            'status' => $this->resource->status->value,
            'response_status' => $this->resource->response_status,
            'latency_ms' => $this->resource->latency_ms,
            'error' => $this->resource->error,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
