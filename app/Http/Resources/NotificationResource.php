<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Notification $resource
 */
class NotificationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'batch_id' => $this->resource->batch_id,
            'template_id' => $this->resource->template_id,
            'channel' => $this->resource->channel->value,
            'priority' => $this->resource->priority->value,
            'status' => $this->resource->status->value,
            'recipient' => $this->resource->recipient,
            'content' => $this->resource->content,
            'payload' => $this->resource->payload,
            'provider_message_id' => $this->resource->provider_message_id,
            'attempts' => $this->resource->attempts,
            'last_error' => $this->resource->last_error,
            'scheduled_at' => $this->resource->scheduled_at?->toIso8601String(),
            'dispatched_at' => $this->resource->dispatched_at?->toIso8601String(),
            'delivered_at' => $this->resource->delivered_at?->toIso8601String(),
            'cancelled_at' => $this->resource->cancelled_at?->toIso8601String(),
            'correlation_id' => $this->resource->correlation_id,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
            'attempts_log' => $this->whenLoaded(
                'attemptsLog',
                fn () => NotificationAttemptResource::collection($this->resource->attemptsLog)
            ),
        ];
    }
}
