<?php

namespace App\Http\Requests\Upload;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `multipart/form-data` with a `file` field. Allowed types and size come from config/api.php.
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `mimes` checks the real file content, not only the extension the client sent.
            'file' => [
                'required',
                'file',
                'max:'.config('api.uploads.max_kb'),
                'mimes:'.implode(',', config('api.uploads.mimes')),
            ],
        ];
    }
}
