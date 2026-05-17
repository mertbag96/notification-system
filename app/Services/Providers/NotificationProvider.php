<?php

namespace App\Services\Providers;

use App\Data\NotificationData;
use App\Data\ProviderResponseData;

interface NotificationProvider
{
    public function send(NotificationData $data): ProviderResponseData;
}
