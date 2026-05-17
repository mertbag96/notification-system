<?php

namespace Tests\Feature\Idempotency;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeating_a_request_with_same_idempotency_key_returns_cached_response(): void
    {
        Queue::fake();

        $payload = [
            'channel' => 'sms',
            'recipient' => '+14155550100',
            'content' => 'Hello',
        ];

        $first = $this->withHeader('Idempotency-Key', 'abc-123')
            ->postJson('/api/v1/notifications', $payload);

        $second = $this->withHeader('Idempotency-Key', 'abc-123')
            ->postJson('/api/v1/notifications', $payload);

        $first->assertCreated();
        $second->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Notification::query()->count());
        $this->assertSame('true', $second->headers->get('Idempotent-Replay'));
    }

    public function test_reusing_key_with_different_payload_returns_conflict(): void
    {
        Queue::fake();

        $this->withHeader('Idempotency-Key', 'conflict-key')
            ->postJson('/api/v1/notifications', [
                'channel' => 'sms',
                'recipient' => '+14155550100',
                'content' => 'First',
            ])->assertCreated();

        $this->withHeader('Idempotency-Key', 'conflict-key')
            ->postJson('/api/v1/notifications', [
                'channel' => 'sms',
                'recipient' => '+14155550100',
                'content' => 'Different',
            ])->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'idempotency_conflict');
    }

    public function test_omitting_idempotency_key_does_not_dedupe(): void
    {
        Queue::fake();

        $payload = [
            'channel' => 'sms',
            'recipient' => '+14155550100',
            'content' => 'Hello',
        ];

        $this->postJson('/api/v1/notifications', $payload)->assertCreated();
        $this->postJson('/api/v1/notifications', $payload)->assertCreated();

        $this->assertSame(2, Notification::query()->count());
    }
}
