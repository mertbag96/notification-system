<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Console\Command;

class DispatchScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch-scheduled {--limit=500}';

    protected $description = 'Queue notifications whose scheduled_at has elapsed.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dispatched = 0;

        Notification::query()
            ->dueForDispatch()
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->each(function (Notification $notification) use (&$dispatched) {
                $notification->update(['status' => NotificationStatus::Queued]);
                SendNotificationJob::dispatch($notification->id)
                    ->onQueue($notification->priority->queueName());
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} scheduled notifications.");

        return self::SUCCESS;
    }
}
