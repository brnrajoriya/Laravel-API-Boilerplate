<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesApiRequests;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Support\Str;

/**
 * Replaces Laravel's `make:controller`. When a model is given (`--model`, `--api` / `--resource`
 * which infer the model from the name, or `make:model -mcr / --api / -a`), it generates the boilerplate's API controller
 * (`stubs/api/controller.stub`) plus `Requests/{Model}/Index|Store|UpdateRequest`.
 * Every other variant (plain, invokable, singleton, nested, --type) behaves exactly like Laravel.
 */
class ApiControllerMakeCommand extends ControllerMakeCommand
{
    use GeneratesApiRequests;

    public function handle()
    {
        // `make:controller RoleController --api` → treat it as `--model=Role`.
        if (! $this->option('model') && ($this->option('api') || $this->option('resource'))) {
            $this->input->setOption('model', Str::beforeLast(class_basename($this->getNameInput()), 'Controller'));
        }

        $result = parent::handle();

        if ($result !== false && $this->usesApiStub()) {
            $model = class_basename($this->parseModel((string) $this->option('model')));
            $this->components->info(sprintf(
                "Register the route in routes/api.php: Route::apiResource('%s', %s::class)",
                Str::kebab(Str::pluralStudly($model)),
                class_basename($this->qualifyClass($this->getNameInput())),
            ));
        }

        return $result;
    }

    protected function getStub()
    {
        return $this->usesApiStub()
            ? $this->laravel->basePath('stubs/api/controller.stub')
            : parent::getStub();
    }

    /**
     * @param  array<string, string>  $replace
     * @return array<string, string>
     */
    protected function buildModelReplacements(array $replace)
    {
        $replace = parent::buildModelReplacements($replace);

        if (! $this->usesApiStub()) {
            return $replace;
        }

        $model = class_basename($this->parseModel((string) $this->option('model')));

        return array_merge($replace, $this->apiReplacements($model));
    }

    /**
     * API controllers always get the folder style form requests, with or without `--requests`.
     *
     * @param  array<string, string>  $replace
     * @param  string  $modelClass
     * @return array<string, string>
     */
    protected function buildFormRequestReplacements(array $replace, $modelClass)
    {
        if (! $this->usesApiStub()) {
            return parent::buildFormRequestReplacements($replace, $modelClass);
        }

        $this->generateApiRequests($modelClass);

        return $replace;
    }

    private function usesApiStub(): bool
    {
        return $this->option('model')
            && ! $this->option('type')
            && ! $this->option('parent')
            && ! $this->option('singleton')
            && ! $this->option('invokable');
    }
}
