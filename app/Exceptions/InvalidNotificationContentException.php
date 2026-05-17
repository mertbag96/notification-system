<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvalidNotificationContentException extends Exception
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
                ['code' => 'invalid_content', 'message' => $this->getMessage()],
            ],
        ], 422);
    }
}
