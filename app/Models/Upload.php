<?php

namespace App\Models;

use App\Policies\UploadPolicy;
use BrnRajoriya\QueryFlow\Attributes\Queryable;
use BrnRajoriya\QueryFlow\Concerns\HasQueryFlow;
use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Queryable(
    filterable: ['id', 'original_name', 'mime_type', 'size', 'created_at'],
    searchable: ['original_name'],
)]
#[Fillable(['user_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
#[Hidden(['disk', 'path'])]
#[UsePolicy(UploadPolicy::class)]
class Upload extends Model
{
    /** @use HasFactory<UploadFactory> */
    use HasFactory, HasQueryFlow;

    protected static function booted(): void
    {
        // Remove the stored file together with the record.
        static::deleted(fn (Upload $upload) => Storage::disk($upload->disk)->delete($upload->path));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Public URL of the file (`php artisan storage:link` is required for the `public` disk).
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk($this->disk)->url($this->path));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
