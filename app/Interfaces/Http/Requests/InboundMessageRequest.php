<?php

namespace App\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class InboundMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string'],
            'influencer_id' => ['required', 'string'],
            'platform' => ['required', 'string'],
            'external_user_id' => ['required_with:messages', 'nullable', 'string'],
            'username' => ['nullable', 'string'],
            'messages' => ['required_without:payload', 'array', 'min:1'],
            'messages.*.external_message_id' => ['required_with:messages', 'string'],
            'messages.*.text' => ['required_with:messages', 'string'],
            'messages.*.received_at' => ['nullable', 'date'],
            'messages.*.metadata' => ['nullable', 'array'],
            'messages.*.raw_payload' => ['nullable', 'array'],
            'payload' => ['required_without:messages', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('messages') && blank($this->input('external_user_id'))) {
                $validator->errors()->add('external_user_id', 'The external user id field is required when messages are present.');
            }
        });
    }
}
