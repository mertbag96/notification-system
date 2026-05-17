<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CancelNotificationAction;
use App\Actions\CreateNotificationAction;
use App\Actions\ListNotificationsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListNotificationsRequest;
use App\Http\Requests\Api\V1\StoreNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function index(
        ListNotificationsRequest $request,
        ListNotificationsAction $action,
    ): JsonResponse {
        $page = $action->execute($request->validated());

        return response()->json([
            'data' => NotificationResource::collection($page->items())->resolve(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'errors' => [],
        ]);
    }

    public function store(
        StoreNotificationRequest $request,
        CreateNotificationAction $action,
    ): JsonResponse {
        $notification = $action->execute($request->validated());

        return response()->json([
            'data' => (new NotificationResource($notification))->resolve(),
            'meta' => null,
            'errors' => [],
        ], 201);
    }

    public function show(Notification $notification): JsonResponse
    {
        $notification->load(['attemptsLog' => fn ($q) => $q->latest()->limit(10)]);

        return response()->json([
            'data' => (new NotificationResource($notification))->resolve(),
            'meta' => null,
            'errors' => [],
        ]);
    }

    public function cancel(
        Notification $notification,
        CancelNotificationAction $action,
    ): JsonResponse {
        $updated = $action->execute($notification);

        return response()->json([
            'data' => (new NotificationResource($updated))->resolve(),
            'meta' => null,
            'errors' => [],
        ]);
    }
}
