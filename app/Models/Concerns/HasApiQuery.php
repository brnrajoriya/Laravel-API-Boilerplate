<?php

namespace App\Models\Concerns;

use App\Support\ApiQueryColumns;
use App\Support\QueryOperations;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Query scopes that turn validated list parameters into a safe Eloquent query.
 * Every column / relation is checked against the model's whitelist (see ApiQueryColumns).
 *
 *   Post::query()
 *       ->apiSelect(['id', 'title'])
 *       ->apiWith(['author'])
 *       ->search('laravel')
 *       ->applyOperations($operations)
 *       ->apiOrderBy('created_at', 'desc')
 *       ->paginate(25);
 */
trait HasApiQuery
{
    /**
     * @param  Builder<static>  $query
     * @param  list<string>  $columns
     */
    #[Scope]
    protected function apiSelect(Builder $query, array $columns): void
    {
        if ($columns === []) {
            return;
        }

        $this->ensureAllowed($columns, ApiQueryColumns::filterable($this), 'select', 'Column');

        // The key is always needed to eager load relations and build URLs.
        $query->select(array_values(array_unique([$this->getKeyName(), ...$columns])));
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<string>  $relations
     */
    #[Scope]
    protected function apiWith(Builder $query, array $relations): void
    {
        if ($relations !== []) {
            $this->ensureAllowed($relations, ApiQueryColumns::includable($this), 'with', 'Relation');
            $query->with($relations);
        }
    }

    /**
     * Case-insensitive `LIKE %keyword%` over `$searchable` columns.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $keyword): void
    {
        $columns = ApiQueryColumns::searchable($this);
        $keyword = trim((string) $keyword);

        if ($keyword === '' || $columns === []) {
            return;
        }

        // Escape LIKE wildcards with `!` - works the same on MySQL, PostgreSQL, SQLite and SQL Server
        // (SQLite has no default escape character, so `\%` would not work there).
        $like = '%'.mb_strtolower(strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_'])).'%';
        $grammar = $query->getQuery()->getGrammar();

        $query->where(function (Builder $q) use ($columns, $like, $grammar): void {
            foreach ($columns as $column) {
                $q->orWhereRaw('LOWER('.$grammar->wrap($column).") LIKE ? ESCAPE '!'", [$like]);
            }
        });
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<int, array<string, mixed>>  $operations
     */
    #[Scope]
    protected function applyOperations(Builder $query, array $operations): void
    {
        QueryOperations::apply($query, $operations);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function apiGroupBy(Builder $query, ?string $column): void
    {
        if ($column) {
            $this->ensureAllowed([$column], ApiQueryColumns::filterable($this), 'group_by', 'Column');
            $query->groupBy($column);
        }
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function apiOrderBy(Builder $query, ?string $column, string $direction = 'desc'): void
    {
        $column = $column ?: $this->getKeyName();
        $this->ensureAllowed([$column], ApiQueryColumns::sortable($this), 'order_by', 'Column');

        $query->orderBy($column, strtolower($direction) === 'asc' ? 'asc' : 'desc');
    }

    /**
     * Eager load whitelisted relations on an already fetched model (`?with=author,tags`).
     *
     * @param  list<string>  $relations
     */
    public function loadIncludes(array $relations): static
    {
        if ($relations !== []) {
            $this->ensureAllowed($relations, ApiQueryColumns::includable($this), 'with', 'Relation');
            $this->load($relations);
        }

        return $this;
    }

    /**
     * @param  list<string>  $values
     * @param  list<string>  $allowed
     */
    private function ensureAllowed(array $values, array $allowed, string $parameter, string $label): void
    {
        $invalid = array_diff($values, $allowed);

        if ($invalid !== []) {
            throw ValidationException::withMessages([
                $parameter => sprintf('%s [%s] is not allowed.', $label, implode(', ', $invalid)),
            ]);
        }
    }
}
