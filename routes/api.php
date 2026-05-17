<?php

use App\Http\Controllers\Api\V1\BatchController;
use App\Http\Controllers\Api\V1\DocsController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.key', 'idempotency'])
    ->prefix('v1')
    ->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
        Route::post('notifications', [NotificationController::class, 'store'])->name('api.v1.notifications.store');
        Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('api.v1.notifications.show');
        Route::post('notifications/{notification}/cancel', [NotificationController::class, 'cancel'])->name('api.v1.notifications.cancel');

        Route::post('notifications/batch', [BatchController::class, 'store'])->name('api.v1.batches.store');
        Route::get('batches/{batch}', [BatchController::class, 'show'])->name('api.v1.batches.show');

        Route::get('metrics', MetricsController::class)->name('api.v1.metrics');
        Route::get('health', HealthController::class)->name('api.v1.health');
    });

Route::get('docs', DocsController::class)->name('api.docs');
Route::get('openapi.yaml', DocsController::class)->name('api.openapi');
