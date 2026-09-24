<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

/**
 * Applies client supplied filter "operations" to an Eloquent query - safely.
 *
 *   operations[0][code]=where
 *   operations[0][parameters][column]=status
 *   operations[0][parameters][operator]==
 *   operations[0][parameters][value]=active
 *
 * Compared to a plain `switch`, this class:
 *  - only allows whitelisted columns / relations (see ApiQueryColumns) and SQL operators,
 *  - wraps all `where` operations in ONE nested group, so an `or_where` can never escape the
 *    constraints the controller added itself (e.g. `where user_id = me`),
 *  - rejects unknown codes and malformed parameters with a 422 instead of silently ignoring them.
 */
final class QueryOperations
{
    public const OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like'];

    public const CODES = [
        'where', 'or_where',
        'where_date', 'where_month', 'where_day', 'where_year', 'where_time',
        'where_in', 'where_not_in', 'or_where_in', 'or_where_not_in',
        'where_between', 'or_where_between', 'where_not_between', 'or_where_not_between',
        'where_null', 'where_not_null', 'or_where_null', 'or_where_not_null',
        'where_column', 'or_where_column',
        'where_has', 'has',
        'having',
    ];

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return self::CODES;
    }

    /**
     * @template TBuilder of Builder<covariant Model>
     *
     * @param  TBuilder  $query
     * @param  array<int, array<string, mixed>>  $operations
     * @return TBuilder
     */
    public static function apply(Builder $query, array $operations): Builder
    {
        if ($operations === []) {
            return $query;
        }

        $model = $query->getModel();
        $having = [];

        $query->where(function (Builder $group) use ($operations, $model, &$having): void {
            foreach (array_values($operations) as $index => $operation) {
                $code = is_string($operation['code'] ?? null) ? $operation['code'] : '';

                if (! in_array($code, self::CODES, true)) {
                    self::invalid("operations.{$index}.code", "Unknown operation [{$code}].");
                }

                if ($code === 'having') {
                    $having[$index] = $operation; // HAVING cannot live inside a WHERE group

                    continue;
                }

                self::applyOne($group, $model, $code, $operation, $index);
            }
        });

        foreach ($having as $index => $operation) {
            self::applyOne($query, $model, 'having', $operation, $index);
        }

        return $query;
    }

    /**
     * @param  Builder<covariant Model>  $q
     * @param  array<string, mixed>  $operation
     */
    private static function applyOne(Builder $q, Model $model, string $code, array $operation, int $index): void
    {
        $p = is_array($operation['parameters'] ?? null) ? $operation['parameters'] : [];
        $at = "operations.{$index}.parameters";

        $column = fn (string $key = 'column') => self::column($model, $p[$key] ?? null, "{$at}.{$key}");
        $operator = fn () => self::operator($p['operator'] ?? '=', "{$at}.operator");
        $value = fn () => self::scalar($p['value'] ?? null, "{$at}.value");
        $values = fn () => self::scalars($p['values'] ?? null, "{$at}.values");
        $range = fn () => self::scalars($p['values'] ?? null, "{$at}.values", exactly: 2);

        match ($code) {
            'where' => $q->where($column(), $operator(), $value()),
            'or_where' => $q->orWhere($column(), $operator(), $value()),
            'where_date' => $q->whereDate($column(), $operator(), self::dateValue($value())),
            'where_month' => $q->whereMonth($column(), $operator(), self::dateValue($value())),
            'where_day' => $q->whereDay($column(), $operator(), self::dateValue($value())),
            'where_year' => $q->whereYear($column(), $operator(), self::dateValue($value())),
            'where_time' => $q->whereTime($column(), $operator(), self::dateValue($value())),
            'where_in' => $q->whereIn($column(), $values()),
            'where_not_in' => $q->whereNotIn($column(), $values()),
            'or_where_in' => $q->orWhereIn($column(), $values()),
            'or_where_not_in' => $q->orWhereNotIn($column(), $values()),
            'where_between' => $q->whereBetween($column(), $range()),
            'or_where_between' => $q->orWhereBetween($column(), $range()),
            'where_not_between' => $q->whereNotBetween($column(), $range()),
            'or_where_not_between' => $q->orWhereNotBetween($column(), $range()),
            'where_null' => $q->whereNull($column()),
            'where_not_null' => $q->whereNotNull($column()),
            'or_where_null' => $q->orWhereNull($column()),
            'or_where_not_null' => $q->orWhereNotNull($column()),
            'where_column' => $q->whereColumn($column('column_1'), $operator(), $column('column_2')),
            'or_where_column' => $q->orWhereColumn($column('column_1'), $operator(), $column('column_2')),
            'where_has', 'has' => self::applyRelation($q, $model, $code, $operation, $p, $index),
            'having' => $q->having($column(), $operator(), self::dateValue($value())),
            default => self::invalid("operations.{$index}.code", "Unknown operation [{$code}]."),
        };
    }

    /**
     * `where_has` with optional condition inside the relation, or a plain `has`.
     *
     * @param  Builder<covariant Model>  $query
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $params
     */
    private static function applyRelation(Builder $query, Model $model, string $code, array $operation, array $params, int $index): void
    {
        $relation = is_string($operation['relation'] ?? null) ? $operation['relation'] : '';

        if (! in_array($relation, ApiQueryColumns::includable($model), true) || ! method_exists($model, $relation)) {
            self::invalid("operations.{$index}.relation", "Relation [{$relation}] is not allowed.");
        }

        if ($code === 'has' || ! isset($params['column'])) {
            $query->has($relation);

            return;
        }

        $instance = $model->{$relation}();
        if (! $instance instanceof Relation) {
            self::invalid("operations.{$index}.relation", "Relation [{$relation}] is not allowed.");
        }

        $at = "operations.{$index}.parameters";
        $column = self::column($instance->getRelated(), $params['column'], "{$at}.column");
        $operator = self::operator($params['operator'] ?? '=', "{$at}.operator");
        $value = self::scalar($params['value'] ?? null, "{$at}.value");

        $query->whereHas($relation, fn (Builder $q) => $q->where($column, $operator, $value));
    }

    private static function column(Model $model, mixed $column, string $path): string
    {
        if (! is_string($column) || ! in_array($column, ApiQueryColumns::filterable($model), true)) {
            self::invalid($path, 'Column ['.(is_scalar($column) ? $column : '?').'] cannot be filtered.');
        }

        return $column;
    }

    private static function operator(mixed $operator, string $path): string
    {
        $operator = is_string($operator) ? strtolower(trim($operator)) : '';

        if (! in_array($operator, self::OPERATORS, true)) {
            self::invalid($path, "Operator [{$operator}] is not allowed.");
        }

        return $operator;
    }

    private static function scalar(mixed $value, string $path): string|int|float|bool|null
    {
        if ($value !== null && ! is_scalar($value)) {
            self::invalid($path, 'The value must be a string, number or boolean.');
        }

        return $value;
    }

    /**
     * Date helpers and HAVING do not accept booleans.
     */
    private static function dateValue(string|int|float|bool|null $value): string|int|float|null
    {
        return is_bool($value) ? (int) $value : $value;
    }

    /**
     * @return list<string|int|float|bool|null>
     */
    private static function scalars(mixed $values, string $path, ?int $exactly = null): array
    {
        if (! is_array($values) || $values === [] || ($exactly !== null && count($values) !== $exactly)) {
            self::invalid($path, $exactly ? "Exactly {$exactly} values are required." : 'At least one value is required.');
        }

        return array_map(fn (mixed $value) => self::scalar($value, $path), array_values($values));
    }

    private static function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
