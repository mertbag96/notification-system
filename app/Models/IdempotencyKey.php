<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $key
 * @property string $request_hash
 * @property int $status_code
 * @property array<string,mixed> $response
 * @property Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'request_hash',
        'status_code',
        'response',
        'expires_at',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'array',
            'status_code' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
