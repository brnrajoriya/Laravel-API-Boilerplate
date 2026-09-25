<?php

namespace App\Http\Requests\Upload;

use BrnRajoriya\QueryFlow\Http\QueryFlowRequest;

/**
 * List my uploads. `select` is not offered: the file URL is built from columns clients never see.
 */
class IndexRequest extends QueryFlowRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'select' => ['prohibited'],
        ];
    }
}
