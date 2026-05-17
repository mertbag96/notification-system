<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(NotificationStatus::class)],
            'channel' => ['nullable', Rule::enum(NotificationChannel::class)],
            'batch_id' => ['nullable', 'uuid', 'exists:notification_batches,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
