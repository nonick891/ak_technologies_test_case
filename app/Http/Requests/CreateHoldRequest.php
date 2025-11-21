<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateHoldRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Add auth logic if needed; open by default
        return true;
    }

    public function rules(): array
    {
        return [
            // Validate header as required UUID
            'Idempotency-Key' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * Map validation to header bag.
     */
    protected function prepareForValidation(): void
    {
        // Move header into input so standard validator can use it
        $this->merge([
            'Idempotency-Key' => $this->header('Idempotency-Key'),
        ]);
    }

    /**
     * Convenience method to get the idempotency key.
     */
    public function getIdempotencyKey(): string
    {
        return $this->header('Idempotency-Key');
    }

    /**
     * Customize validation error response structure if desired.
     */
    public function messages(): array
    {
        return [
            'Idempotency-Key.required' => 'The Idempotency-Key header is required.',
            'Idempotency-Key.uuid'     => 'The Idempotency-Key header must be a valid UUID.',
        ];
    }
}
