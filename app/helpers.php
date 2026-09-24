<?php

use App\Support\QueryOperations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('addOperationsInQuery')) {
    /**
     * Apply client supplied filter operations to a query (kept for backwards compatibility).
     * Prefer the `->applyOperations($operations)` scope from the HasApiQuery trait.
     *
     * @template TBuilder of Builder<covariant Model>
     *
     * @param  TBuilder  $query
     * @param  array<int, array<string, mixed>>  $operations
     * @return TBuilder
     */
    function addOperationsInQuery(Builder $query, array $operations): Builder
    {
        return QueryOperations::apply($query, $operations);
    }
}
