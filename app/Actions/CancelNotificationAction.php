<?php

namespace App\Actions;

use App\Enums\NotificationStatus;
use App\Exceptions\NotificationNotCancellableException;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

final class CancelNotificationAction
{
    public function execute(Notification $notification): Notification
    {
        return DB::transaction(function () use ($notification) {
            $fresh = Notification::query()->lockForUpdate()->findOrFail($notification->id);

            if (! $fresh->status->isCancellable()) {
                throw new NotificationNotCancellableException(
                    "Cannot cancel notification in status '{$fresh->status->value}'."
                );
            }

            $fresh->update([
                'status' => NotificationStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            return $fresh->refresh();
        });
    }
}
