<?php

namespace App\Actions;

use App\Exceptions\InvalidNotificationContentException;
use App\Models\NotificationBatch;
use App\Support\CorrelationId;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateBatchNotificationAction
{
    public function __construct(
        private readonly CreateNotificationAction $createNotification,
    ) {}

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array{batch: NotificationBatch, errors: array<int,array{index:int,message:string}>}
     */
    public function execute(array $items): array
    {
        $correlationId = CorrelationId::current();

        return DB::transaction(function () use ($items, $correlationId) {
            $batch = NotificationBatch::query()->create([
                'correlation_id' => $correlationId,
                'total' => count($items),
                'accepted' => 0,
                'rejected' => 0,
            ]);

            $accepted = 0;
            $rejected = 0;
            $errors = [];

            foreach ($items as $index => $item) {
                try {
                    $this->createNotification->execute($item, $batch->id, $correlationId);
                    $accepted++;
                } catch (InvalidNotificationContentException|Throwable $e) {
                    $rejected++;
                    $errors[] = [
                        'index' => $index,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $batch->update([
                'accepted' => $accepted,
                'rejected' => $rejected,
            ]);

            return [
                'batch' => $batch->refresh(),
                'errors' => $errors,
            ];
        });
    }
}
