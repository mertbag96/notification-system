<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationNotCancellableException extends Exception
{
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'data' => null,
            'meta' => null,
            'errors' => [
                ['code' => 'not_cancellable', 'message' => $this->getMessage() ?: 'Notification cannot be cancelled in its current state.'],
            ],
        ], 409);
    }
}
