<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves which columns / relations of a model the API may expose to query parameters.
 *
 * A model can declare any of these (all optional):
 *
 *   protected array $filterable = ['id', 'status', 'created_at']; // filter, select, group_by
 *   protected array $sortable   = ['id', 'name', 'created_at'];   // order_by (defaults to filterable)
 *   protected array $searchable = ['name', 'description'];        // keyword search
 *   protected array $includable = ['author', 'comments'];         // with / where_has / has
 *
 * Without `$filterable`, the primary key, fillable columns and timestamps are filterable,
 * minus `$hidden` ones (so e.g. `password` can never be filtered on).
 */
final class ApiQueryColumns
{
    /** @var array<class-string, array<string, list<string>>> */
    private static array $cache = [];

    /**
     * @return list<string>
     */
    public static function filterable(Model $model): array
    {
        return self::remember($model, 'filterable', function () use ($model): array {
            $declared = self::declared($model, 'filterable');

            if ($declared !== null) {
                return $declared;
            }

            $columns = [$model->getKeyName(), ...$model->getFillable()];

            if ($model->usesTimestamps()) {
                array_push($columns, $model->getCreatedAtColumn(), $model->getUpdatedAtColumn());
            }

            return array_values(array_diff(array_unique(array_filter($columns)), $model->getHidden()));
        });
    }

    /**
     * @return list<string>
     */
    public static function sortable(Model $model): array
    {
        return self::remember($model, 'sortable', fn () => self::declared($model, 'sortable') ?? self::filterable($model));
    }

    /**
     * @return list<string>
     */
    public static function searchable(Model $model): array
    {
        return self::remember($model, 'searchable', fn () => self::declared($model, 'searchable') ?? []);
    }

    /**
     * @return list<string>
     */
    public static function includable(Model $model): array
    {
        return self::remember($model, 'includable', fn () => self::declared($model, 'includable') ?? []);
    }

    /**
     * @return list<string>|null
     */
    private static function declared(Model $model, string $property): ?array
    {
        if (! property_exists($model, $property)) {
            return null;
        }

        $value = (fn () => $this->{$property})->call($model);

        return is_array($value) ? array_values($value) : null;
    }

    /**
     * Whitelists never change at runtime, so they are computed once per model class.
     *
     * @param  callable(): list<string>  $resolve
     * @return list<string>
     */
    private static function remember(Model $model, string $kind, callable $resolve): array
    {
        return self::$cache[$model::class][$kind] ??= $resolve();
    }
}
