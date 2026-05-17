<?php

namespace App\Providers;

use App\Services\Providers\NotificationProvider;
use App\Services\Providers\WebhookSiteProvider;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationProvider::class, WebhookSiteProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
