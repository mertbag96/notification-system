<?php

namespace Tests\Unit\Enums;

use App\Enums\NotificationStatus;
use Tests\TestCase;

class NotificationStatusTest extends TestCase
{
    public function test_terminal_statuses_are_identified(): void
    {
        $this->assertTrue(NotificationStatus::Sent->isTerminal());
        $this->assertTrue(NotificationStatus::Failed->isTerminal());
        $this->assertTrue(NotificationStatus::Cancelled->isTerminal());
        $this->assertTrue(NotificationStatus::DeadLettered->isTerminal());

        $this->assertFalse(NotificationStatus::Pending->isTerminal());
        $this->assertFalse(NotificationStatus::Queued->isTerminal());
        $this->assertFalse(NotificationStatus::Processing->isTerminal());
    }

    public function test_only_pending_and_queued_are_cancellable(): void
    {
        $this->assertTrue(NotificationStatus::Pending->isCancellable());
        $this->assertTrue(NotificationStatus::Queued->isCancellable());

        $this->assertFalse(NotificationStatus::Processing->isCancellable());
        $this->assertFalse(NotificationStatus::Sent->isCancellable());
        $this->assertFalse(NotificationStatus::Failed->isCancellable());
        $this->assertFalse(NotificationStatus::Cancelled->isCancellable());
    }
}
