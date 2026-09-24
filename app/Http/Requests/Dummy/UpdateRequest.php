<?php

namespace App\Http\Requests\Dummy;

use App\Enums\DummyCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for updating a dummy. Use `sometimes` so partial updates (PATCH) work.
 */
class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:100'],
            'category' => ['sometimes', 'required', Rule::enum(DummyCategory::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
