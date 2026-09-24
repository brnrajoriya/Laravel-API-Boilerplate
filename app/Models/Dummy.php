<?php

namespace App\Models;

use App\Enums\DummyCategory;
use App\Models\Concerns\HasApiQuery;
use Database\Factories\DummyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Example resource generated with `php artisan make:model Dummy -mfs` +
 * `php artisan make:controller Api/V1/DummyController --model=Dummy`. Copy it for your own resources.
 */
#[Fillable(['title', 'category', 'description'])]
class Dummy extends Model
{
    /** @use HasFactory<DummyFactory> */
    use HasApiQuery, HasFactory;

    /**
     * API query whitelists (see App\Support\ApiQueryColumns).
     * `$filterable` / `$sortable` default to id + fillable + timestamps.
     *
     * @var list<string>
     */
    protected array $searchable = ['title', 'description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DummyCategory::class,
        ];
    }
}
