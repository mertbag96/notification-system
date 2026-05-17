<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // PHPUnit sets NOTIFICATION_API_KEY in phpunit.xml, but DOTENV/`config.php` caches can populate
        // `notifications.api.key` from `.env`; force the bootstrap value inside the test application.
        $key = '';

        if (array_key_exists('NOTIFICATION_API_KEY', $_SERVER)) {
            $key = $_SERVER['NOTIFICATION_API_KEY'];
        } elseif (array_key_exists('NOTIFICATION_API_KEY', $_ENV)) {
            $key = $_ENV['NOTIFICATION_API_KEY'];
        }

        config()->set('notifications.api.key', (string) $key);
    }
}
