<?php

namespace App\Http\Requests;

use App\Support\QueryOperations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation for every list endpoint. Generated `{Model}\IndexRequest` classes extend it.
 *
 * Query parameters (all optional):
 *   page, per_page (1-100, default 25), order_by (default id), order_type (asc|desc, default desc),
 *   keyword, select[] , with (comma separated), group_by, return_type (data|count), operations[]
 *
 * Column / relation names are checked against the model's whitelist by the HasApiQuery scopes.
 */
class ApiIndexRequest extends FormRequest
{
    public const MAX_PER_PAGE = 100;

    public const DEFAULT_PER_PAGE = 25;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'order_by' => ['sometimes', 'string', 'max:64'],
            'order_type' => ['sometimes', Rule::in(['asc', 'desc'])],
            'keyword' => ['sometimes', 'nullable', 'string', 'max:100'],
            'select' => ['sometimes', 'array', 'max:50'],
            'select.*' => ['string', 'max:64'],
            'with' => ['sometimes', 'nullable', 'string', 'max:255'],
            'group_by' => ['sometimes', 'nullable', 'string', 'max:64'],
            'return_type' => ['sometimes', Rule::in(['data', 'count'])],
            'operations' => ['sometimes', 'array', 'max:25'],
            'operations.*.code' => ['required', Rule::in(QueryOperations::codes())],
            'operations.*.relation' => ['sometimes', 'string', 'max:64'],
            'operations.*.parameters' => ['sometimes', 'array'],
        ];
    }

    public function perPage(): int
    {
        return (int) $this->validated('per_page', self::DEFAULT_PER_PAGE);
    }

    public function orderBy(): string
    {
        return (string) $this->validated('order_by', 'id');
    }

    public function orderType(): string
    {
        return (string) $this->validated('order_type', 'desc');
    }

    public function keyword(): ?string
    {
        return $this->validated('keyword');
    }

    /**
     * @return list<string>
     */
    public function selectColumns(): array
    {
        return array_values($this->validated('select', []));
    }

    /**
     * `?with=author,comments` → ['author', 'comments'].
     *
     * @return list<string>
     */
    public function relations(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->validated('with', '')))));
    }

    public function groupBy(): ?string
    {
        return $this->validated('group_by');
    }

    public function wantsCount(): bool
    {
        return $this->validated('return_type', 'data') === 'count';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function operations(): array
    {
        return $this->validated('operations', []);
    }
}
