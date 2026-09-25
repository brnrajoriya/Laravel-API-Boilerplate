<?php

namespace App\Models;

use App\Enums\DummyCategory;
use BrnRajoriya\QueryFlow\Attributes\Queryable;
use BrnRajoriya\QueryFlow\Concerns\HasQueryFlow;
use Database\Factories\DummyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Example resource, generated with `php artisan make:model Dummy -a` and then filled in.
 * Copy it for your own resources.
 *
 * QueryFlow whitelist: `filterable` / `sortable` default to id + fillable + timestamps.
 */
#[Queryable(searchable: ['title', 'description'])]
#[Fillable(['title', 'category', 'description'])]
class Dummy extends Model
{
    /** @use HasFactory<DummyFactory> */
    use HasFactory, HasQueryFlow, SoftDeletes;

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
