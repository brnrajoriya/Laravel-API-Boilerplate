<?php

namespace App\Http\Requests\Dummy;

use BrnRajoriya\QueryFlow\Http\QueryFlowRequest;

/**
 * List dummies: every QueryFlow parameter (page, per_page, order_by, keyword, filter,
 * operations, with, with_count, return_type, ...) is validated by QueryFlowRequest.
 * Add endpoint specific rules below.
 */
class IndexRequest extends QueryFlowRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            //
        ];
    }
}
