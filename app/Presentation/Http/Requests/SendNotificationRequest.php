<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Application\DTOs\SendNotificationDTO;
use App\Domain\Notification\Enums\Channel;
use App\Domain\Notification\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel'         => ['required', 'string', Rule::enum(Channel::class)],
            'priority'        => ['required', 'string', Rule::enum(Priority::class)],
            'message'         => ['required', 'string', 'min:1', 'max:10000'],
            'recipient_ids'   => ['required', 'array', 'min:1', 'max:1000'],
            'recipient_ids.*' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required'       => 'The channel field is required.',
            'channel.Illuminate\Validation\Rules\Enum' => 'Channel must be one of: email, sms.',
            'priority.required'      => 'The priority field is required.',
            'priority.Illuminate\Validation\Rules\Enum' => 'Priority must be one of: high, normal, low.',
            'recipient_ids.required' => 'At least one recipient ID is required.',
            'recipient_ids.max'      => 'A maximum of 1 000 recipients per request is allowed.',
        ];
    }

    public function toDTO(): SendNotificationDTO
    {
        $validated = $this->validated();

        return new SendNotificationDTO(
            channel: Channel::from($validated['channel']),
            priority: Priority::from($validated['priority']),
            message: $validated['message'],
            recipientIds: array_map('intval', $validated['recipient_ids']),
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );
    }
}
