<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

class PruneIdempotencyKeysCommand extends Command
{
    protected $signature = 'notifications:prune-idempotency';

    protected $description = 'Delete expired idempotency keys.';

    public function handle(): int
    {
        $deleted = IdempotencyKey::query()->where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deleted} expired idempotency keys.");

        return self::SUCCESS;
    }
}
