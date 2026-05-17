<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,array<int,mixed>>
     */
    public function rules(): array
    {
        $max = (int) config('notifications.api.max_batch_size', 1000);

        return [
            'notifications' => ['required', 'array', 'min:1', "max:{$max}"],
            'notifications.*.channel' => ['required', Rule::enum(NotificationChannel::class)],
            'notifications.*.priority' => ['nullable', Rule::enum(NotificationPriority::class)],
            'notifications.*.recipient' => ['required', 'string', 'max:512'],
            'notifications.*.content' => ['required_without:notifications.*.template_id', 'nullable', 'string'],
            'notifications.*.payload' => ['nullable', 'array'],
            'notifications.*.scheduled_at' => ['nullable', 'date', 'after:now'],
            'notifications.*.template_id' => ['nullable', 'uuid', 'exists:notification_templates,id'],
            'notifications.*.template_variables' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = (array) $this->input('notifications', []);

            foreach ($items as $index => $item) {
                $channel = NotificationChannel::tryFrom((string) ($item['channel'] ?? ''));
                if ($channel === null) {
                    continue;
                }

                $recipient = (string) ($item['recipient'] ?? '');

                $recipientValid = match ($channel) {
                    NotificationChannel::Sms => preg_match('/^\+[1-9]\d{6,14}$/', $recipient) === 1,
                    NotificationChannel::Email => filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false,
                    NotificationChannel::Push => mb_strlen($recipient) >= 8 && mb_strlen($recipient) <= 512,
                };

                if (! $recipientValid) {
                    $validator->errors()->add("notifications.{$index}.recipient", "Recipient is invalid for channel {$channel->value}.");
                }

                $content = (string) ($item['content'] ?? '');

                if ($content !== '' && mb_strlen($content) > $channel->contentLimit()) {
                    $validator->errors()->add(
                        "notifications.{$index}.content",
                        "Content exceeds {$channel->contentLimit()} characters for channel {$channel->value}."
                    );
                }
            }
        });
    }
}
