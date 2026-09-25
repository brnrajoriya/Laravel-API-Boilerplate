<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Writes the files around an API controller from `stubs/api/*.stub`:
 *
 *   app/Http/Requests/{Model}/IndexRequest.php, StoreRequest.php, UpdateRequest.php
 *   app/Http/Resources/{Model}Resource.php
 *   app/Policies/{Model}Policy.php
 *   tests/Feature/Api/{Model}ApiTest.php
 *
 * Existing files are never overwritten.
 */
trait GeneratesApiFiles
{
    protected function generateApiFiles(string $modelClass, string $controllerClass): void
    {
        $this->generateApiRequests($modelClass);
        $this->generateApiPolicy($modelClass);

        $model = class_basename($modelClass);
        $replace = $this->apiReplacements($modelClass, $controllerClass);

        $this->writeApiStub('resource', $this->laravel->path("Http/Resources/{$model}Resource.php"), [
            ...$replace, '{{ namespace }}' => $this->rootNamespace().'Http\Resources', '{{ class }}' => "{$model}Resource",
        ], 'Resource');

        $this->writeApiStub('test', $this->laravel->basePath("tests/Feature/Api/{$model}ApiTest.php"), [
            ...$replace, '{{ class }}' => "{$model}ApiTest",
        ], 'Test');
    }

    protected function generateApiRequests(string $modelClass): void
    {
        $model = class_basename($modelClass);
        $replace = [...$this->apiReplacements($modelClass), '{{ namespace }}' => $this->rootNamespace().'Http\Requests\\'.$model];

        foreach (['index' => 'IndexRequest', 'store' => 'StoreRequest', 'update' => 'UpdateRequest'] as $stub => $class) {
            $this->writeApiStub("request.{$stub}", $this->laravel->path("Http/Requests/{$model}/{$class}.php"), $replace, 'Request');
        }
    }

    protected function generateApiPolicy(string $modelClass): void
    {
        $model = class_basename($modelClass);

        $this->writeApiStub('policy', $this->laravel->path("Policies/{$model}Policy.php"), [
            ...$this->apiReplacements($modelClass),
            '{{ namespace }}' => $this->rootNamespace().'Policies',
            '{{ class }}' => "{$model}Policy",
        ], 'Policy');
    }

    /**
     * Placeholders shared by the API stubs.
     *
     * @return array<string, string>
     */
    protected function apiReplacements(string $modelClass, ?string $controllerClass = null): array
    {
        $model = class_basename($modelClass);
        $plural = Str::plural(Str::headline($model));

        return [
            '{{ rootNamespace }}' => $this->rootNamespace(),
            '{{ namespacedModel }}' => $modelClass,
            '{{ model }}' => $model,
            '{{ modelVariable }}' => lcfirst($model),
            '{{ modelPluralVariable }}' => Str::camel(Str::pluralStudly($model)),
            '{{ modelPlural }}' => $plural,
            '{{ modelPluralLower }}' => Str::lower($plural),
            '{{ modelLower }}' => Str::lower(Str::headline($model)),
            '{{ routeName }}' => $this->apiRouteName($model),
            '{{ controller }}' => class_basename($controllerClass ?? "{$model}Controller"),
            '{{ restoreMethod }}' => $this->restoreMethod($modelClass),
        ];
    }

    protected function apiRouteName(string $model): string
    {
        return Str::kebab(Str::pluralStudly($model));
    }

    /**
     * A `restore()` action, only for models that already use SoftDeletes.
     */
    private function restoreMethod(string $modelClass): string
    {
        if (! class_exists($modelClass) || ! in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            return '';
        }

        $model = class_basename($modelClass);
        $variable = lcfirst($model);
        $label = Str::lower(Str::headline($model));

        return <<<PHP

    /**
     * Restore a deleted {$label}.
     *
     * @urlParam {$variable} integer required The ID of the {$label}. Example: 1
     */
    public function restore({$model} \${$variable}): JsonResponse
    {
        Gate::authorize('restore', \${$variable});

        \${$variable}->restore();

        return \$this->resource(\${$variable}, {$model}Resource::class, '{$model} restored successfully.');
    }

PHP;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function writeApiStub(string $stub, string $path, array $replace, string $label): void
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if ($this->files->exists($path)) {
            $this->components->warn("{$label} [{$path}] already exists, skipped.");

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, str_replace(
            array_keys($replace),
            array_values($replace),
            $this->files->get($this->laravel->basePath("stubs/api/{$stub}.stub")),
        ));

        $this->components->info(sprintf('%s [%s] created successfully.', $label, $path));
    }
}
