<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateBatchNotificationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBatchNotificationRequest;
use App\Http\Resources\BatchResource;
use App\Models\NotificationBatch;
use Illuminate\Http\JsonResponse;

class BatchController extends Controller
{
    public function store(
        StoreBatchNotificationRequest $request,
        CreateBatchNotificationAction $action,
    ): JsonResponse {
        $result = $action->execute($request->validated()['notifications']);

        return response()->json([
            'data' => (new BatchResource($result['batch']))->resolve(),
            'meta' => null,
            'errors' => $result['errors'],
        ], 201);
    }

    public function show(NotificationBatch $batch): JsonResponse
    {
        return response()->json([
            'data' => (new BatchResource($batch))->resolve(),
            'meta' => null,
            'errors' => [],
        ]);
    }
}
