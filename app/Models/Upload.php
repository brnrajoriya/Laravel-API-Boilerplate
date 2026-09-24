<?php

namespace App\Models;

use App\Models\Concerns\HasApiQuery;
use App\Policies\UploadPolicy;
use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
#[Hidden(['disk', 'path'])]
#[Appends(['url'])]
#[UsePolicy(UploadPolicy::class)]
class Upload extends Model
{
    /** @use HasFactory<UploadFactory> */
    use HasApiQuery, HasFactory;

    /** @var list<string> */
    protected array $filterable = ['id', 'original_name', 'mime_type', 'size', 'created_at'];

    /** @var list<string> */
    protected array $searchable = ['original_name'];

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
