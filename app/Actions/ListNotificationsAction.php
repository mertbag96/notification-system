<?php

namespace App\Actions;

use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListNotificationsAction
{
    /**
     * @param  array{
     *     status?: string|null,
     *     channel?: string|null,
     *     batch_id?: string|null,
     *     from?: string|null,
     *     to?: string|null,
     *     per_page?: int|null,
     * } $filters
     */
    public function execute(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);

        return Notification::query()
            ->when(! empty($filters['status']), fn ($q) => $q->ofStatus($filters['status']))
            ->when(! empty($filters['channel']), fn ($q) => $q->ofChannel($filters['channel']))
            ->when(! empty($filters['batch_id']), fn ($q) => $q->where('batch_id', $filters['batch_id']))
            ->createdBetween($filters['from'] ?? null, $filters['to'] ?? null)
            ->with(['attemptsLog' => fn ($q) => $q->latest()->limit(5)])
            ->latest('created_at')
            ->paginate($perPage);
    }
}
