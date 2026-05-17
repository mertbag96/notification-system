<?php

namespace App\Models;

use Database\Factories\NotificationBatchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $correlation_id
 * @property int $total
 * @property int $accepted
 * @property int $rejected
 */
class NotificationBatch extends Model
{
    /** @use HasFactory<NotificationBatchFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'correlation_id',
        'total',
        'accepted',
        'rejected',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'accepted' => 'integer',
            'rejected' => 'integer',
        ];
    }

    /**
     * @return HasMany<Notification>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
