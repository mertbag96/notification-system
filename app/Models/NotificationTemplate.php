<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use Database\Factories\NotificationTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property NotificationChannel $channel
 * @property string|null $subject
 * @property string $body
 * @property array<int,string>|null $variables
 */
class NotificationTemplate extends Model
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'name',
        'channel',
        'subject',
        'body',
        'variables',
    ];

    /**
     * @return array<string,string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'variables' => 'array',
        ];
    }
}
