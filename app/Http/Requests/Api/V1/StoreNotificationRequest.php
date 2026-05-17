<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPriority;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
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
        return [
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'priority' => ['nullable', Rule::enum(NotificationPriority::class)],
            'recipient' => ['required', 'string', 'max:512'],
            'content' => ['required_without:template_id', 'nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'template_id' => ['nullable', 'uuid', 'exists:notification_templates,id'],
            'template_variables' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $channel = NotificationChannel::from($this->string('channel')->toString());
            $recipient = (string) $this->input('recipient');

            $recipientValid = match ($channel) {
                NotificationChannel::Sms => preg_match('/^\+[1-9]\d{6,14}$/', $recipient) === 1,
                NotificationChannel::Email => filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false,
                NotificationChannel::Push => mb_strlen($recipient) >= 8 && mb_strlen($recipient) <= 512,
            };

            if (! $recipientValid) {
                $validator->errors()->add('recipient', "Recipient is invalid for channel {$channel->value}.");
            }

            $content = (string) $this->input('content', '');

            if ($content !== '' && mb_strlen($content) > $channel->contentLimit()) {
                $validator->errors()->add(
                    'content',
                    "Content exceeds {$channel->contentLimit()} characters for channel {$channel->value}."
                );
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'priority' => $this->input('priority', NotificationPriority::Normal->value),
        ]);
    }
}
