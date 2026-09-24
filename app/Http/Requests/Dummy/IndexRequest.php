<?php

namespace App\Http\Requests\Dummy;

use App\Http\Requests\ApiIndexRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * List dummies: pagination, sorting, search and filter parameters.
 * The shared rules live in ApiIndexRequest; add endpoint specific ones below.
 */
class IndexRequest extends ApiIndexRequest
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
            ...parent::rules(),
            //
        ];
    }
}
