<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Str;

/**
 * Writes `app/Http/Requests/{Model}/{Index,Store,Update}Request.php` from `stubs/api/request.*.stub`.
 * Existing files are never overwritten.
 */
trait GeneratesApiRequests
{
    protected function generateApiRequests(string $modelClass): void
    {
        $model = class_basename($modelClass);
        $namespace = $this->rootNamespace().'Http\\Requests\\'.$model;

        foreach (['index' => 'IndexRequest', 'store' => 'StoreRequest', 'update' => 'UpdateRequest'] as $stub => $class) {
            $path = $this->laravel->path(implode(DIRECTORY_SEPARATOR, ['Http', 'Requests', $model, "{$class}.php"]));

            if ($this->files->exists($path)) {
                $this->components->warn("Request [{$path}] already exists, skipped.");

                continue;
            }

            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, str_replace(
                array_keys($replace = $this->apiReplacements($model, $namespace)),
                array_values($replace),
                $this->files->get($this->laravel->basePath("stubs/api/request.{$stub}.stub")),
            ));

            $this->components->info(sprintf('Request [%s] created successfully.', $path));
        }
    }

    /**
     * Placeholders shared by the API stubs.
     *
     * @return array<string, string>
     */
    protected function apiReplacements(string $model, ?string $namespace = null): array
    {
        $plural = Str::plural(Str::headline($model));

        return array_filter([
            '{{ namespace }}' => $namespace,
            '{{ rootNamespace }}' => $this->rootNamespace(),
            '{{ modelPlural }}' => $plural,
            '{{ modelPluralLower }}' => Str::lower($plural),
            '{{ modelLower }}' => Str::lower(Str::headline($model)),
        ], fn ($value) => $value !== null);
    }
}
