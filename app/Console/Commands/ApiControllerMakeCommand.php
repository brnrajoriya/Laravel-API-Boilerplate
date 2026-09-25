<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GeneratesApiFiles;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Support\Str;

/**
 * Replaces Laravel's `make:controller`. When a model is known (`--model`, `--api` / `--resource`
 * which infer it from the name, or `make:model -a / -c / -r / --api`) it generates the boilerplate's
 * QueryFlow API controller plus its requests, resource, policy and test (see GeneratesApiFiles).
 * Every other variant (plain, invokable, singleton, nested, --type) behaves exactly like Laravel.
 */
class ApiControllerMakeCommand extends ControllerMakeCommand
{
    use GeneratesApiFiles;

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
                "Register the routes in routes/api.php: Route::apiCrud('%s', %s::class)",
                $this->apiRouteName($model),
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

        $modelClass = $this->parseModel((string) $this->option('model'));

        return array_merge($replace, $this->apiReplacements($modelClass, $this->qualifyClass($this->getNameInput())));
    }

    /**
     * API controllers always get their requests, resource, policy and test.
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

        $this->generateApiFiles($modelClass, $this->qualifyClass($this->getNameInput()));

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
