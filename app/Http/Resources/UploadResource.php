<?php

namespace App\Http\Resources;

use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Storage details (disk, path) stay private; clients get a URL.
 *
 * @mixin Upload
 */
class UploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'url' => $this->url,
            'created_at' => $this->created_at,
        ];
    }
}
