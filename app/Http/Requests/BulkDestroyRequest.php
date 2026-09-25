<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `DELETE /api/v1/{resource}` with `{ "ids": [1, 2, 3] }` (max 100).
 */
class BulkDestroyRequest extends FormRequest
{
    public const MAX_IDS = 100;

    public function authorize(): bool
    {
        return true; // every record is checked against its policy in the controller
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_IDS],
            'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->validated('ids')));
    }
}
