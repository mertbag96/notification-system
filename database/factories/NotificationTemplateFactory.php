<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        return [
            'name' => 'tpl_'.Str::lower(Str::random(8)),
            'channel' => NotificationChannel::Sms,
            'subject' => null,
            'body' => 'Hello {{name}}, your code is {{code}}.',
            'variables' => ['name', 'code'],
        ];
    }
}
