<?php

namespace App\Http\Resources;

use App\Models\Dummy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dummy
 */
class DummyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->whenHas('title'),
            'category' => $this->whenHas('category'),
            'description' => $this->whenHas('description'),
            'created_at' => $this->whenHas('created_at'),
            'updated_at' => $this->whenHas('updated_at'),
            'deleted_at' => $this->whenNotNull($this->whenHas('deleted_at')),
        ];
    }
}
