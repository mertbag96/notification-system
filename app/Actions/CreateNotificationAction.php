<?php

namespace App\Actions;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Services\ContentValidator;
use App\Support\CorrelationId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CreateNotificationAction
{
    public function __construct(
        private readonly ContentValidator $validator,
        private readonly RenderTemplateAction $renderTemplate,
    ) {}

    /**
     * @param  array<string,mixed>  $input
     */
    public function execute(array $input, ?string $batchId = null, ?string $correlationId = null): Notification
    {
        $channel = NotificationChannel::from($input['channel']);
        $priority = NotificationPriority::from($input['priority'] ?? NotificationPriority::Normal->value);
        $recipient = (string) $input['recipient'];

        $content = $this->renderTemplate->execute(
            $input['template_id'] ?? null,
            $input['content'] ?? null,
            (array) ($input['template_variables'] ?? []),
        );

        $this->validator->ensureValid($channel, $recipient, $content);

        $correlation = $correlationId ?? CorrelationId::current();

        $scheduledAt = isset($input['scheduled_at']) && $input['scheduled_at'] !== null
            ? Carbon::parse($input['scheduled_at'])
            : null;

        $isScheduled = $scheduledAt !== null && $scheduledAt->isFuture();

        $notification = DB::transaction(fn () => Notification::query()->create([
            'batch_id' => $batchId,
            'template_id' => $input['template_id'] ?? null,
            'channel' => $channel,
            'priority' => $priority,
            'status' => $isScheduled ? NotificationStatus::Pending : NotificationStatus::Queued,
            'recipient' => $recipient,
            'content' => $content,
            'payload' => $input['payload'] ?? null,
            'scheduled_at' => $scheduledAt,
            'correlation_id' => $correlation,
        ]));

        if (! $isScheduled) {
            SendNotificationJob::dispatch($notification->id)
                ->onQueue($priority->queueName());
        }

        return $notification->refresh();
    }
}
