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

    protected function prepareForValidation(): void
    {
        // Prefer character_id; accept legacy influencer_id as alias until clients migrate.
        $characterId = $this->input('character_id') ?: $this->input('influencer_id');
        if (is_string($characterId) && $characterId !== '') {
            $this->merge([
                'character_id' => $characterId,
                'influencer_id' => $characterId,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string'],
            'character_id' => ['required_without:influencer_id', 'nullable', 'string'],
            'influencer_id' => ['required_without:character_id', 'nullable', 'string'],
            'platform' => ['required', 'string'],
            'external_user_id' => ['required_with:messages', 'nullable', 'string'],
            'username' => ['nullable', 'string'],
            'messages' => ['required_without:payload', 'array', 'min:1'],
            'messages.*.external_message_id' => ['required_with:messages', 'string'],
            'messages.*.text' => ['nullable', 'string'],
            'messages.*.content_type' => ['nullable', 'string', 'in:text,image,video,audio,voice,other'],
            'messages.*.media' => ['nullable', 'array'],
            'messages.*.media.*.url' => ['required', 'string', 'url'],
            'messages.*.media.*.type' => ['nullable', 'string'],
            'messages.*.media.*.mime_type' => ['nullable', 'string'],
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

            foreach ((array) $this->input('messages', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $hasText = filled($item['text'] ?? null);
                $hasMedia = is_array($item['media'] ?? null) && $item['media'] !== [];
                if (! $hasText && ! $hasMedia) {
                    $validator->errors()->add(
                        "messages.{$index}",
                        'Each message must include text and/or media.',
                    );
                }
            }
        });
    }
}
