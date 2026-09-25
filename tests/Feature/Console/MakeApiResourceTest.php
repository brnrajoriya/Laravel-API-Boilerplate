<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeApiResourceTest extends TestCase
{
    /** Everything the generators may create for the `Widget` / `Ping` fixtures. */
    private const GENERATED = [
        'app/Models/Widget.php',
        'app/Http/Controllers/WidgetController.php',
        'app/Http/Controllers/Api/V1/WidgetController.php',
        'app/Http/Controllers/PingController.php',
        'app/Http/Requests/Widget',
        'app/Http/Resources/WidgetResource.php',
        'app/Policies/WidgetPolicy.php',
        'tests/Feature/Api/WidgetApiTest.php',
        'database/factories/WidgetFactory.php',
        'database/seeders/WidgetSeeder.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function test_make_controller_with_model_generates_the_full_api_stack(): void
    {
        $output = $this->make('make:controller', ['name' => 'Api/V1/WidgetController', '--model' => 'Widget']);

        $this->assertStringContainsString("Route::apiCrud('widgets', WidgetController::class)", $output);

        $controller = $this->generated('app/Http/Controllers/Api/V1/WidgetController.php');
        $this->assertStringContainsString('namespace App\Http\Controllers\Api\V1;', $controller);
        $this->assertStringContainsString('@group Widgets', $controller);
        $this->assertStringContainsString('QueryFlow::for(Widget::query())', $controller);
        $this->assertStringContainsString("Gate::authorize('update', \$widget);", $controller);
        $this->assertStringContainsString('public function bulkDestroy(BulkDestroyRequest $request): JsonResponse', $controller);
        $this->assertStringContainsString('WidgetResource::class', $controller);
        $this->assertStringNotContainsString('function create(', $controller);
        $this->assertStringNotContainsString('function restore(', $controller); // model has no SoftDeletes

        $this->assertStringContainsString('extends QueryFlowRequest', $this->generated('app/Http/Requests/Widget/IndexRequest.php'));
        $this->generated('app/Http/Requests/Widget/StoreRequest.php');
        $this->generated('app/Http/Requests/Widget/UpdateRequest.php');
        $this->assertStringContainsString('class WidgetResource extends JsonResource', $this->generated('app/Http/Resources/WidgetResource.php'));
        $this->assertStringContainsString('return $this->isOwner($user, $widget);', $this->generated('app/Policies/WidgetPolicy.php'));
        $this->assertStringContainsString("/api/v1/widgets'", $this->generated('tests/Feature/Api/WidgetApiTest.php'));

        $model = $this->generated('app/Models/Widget.php');
        $this->assertStringContainsString('#[Queryable(', $model);
        $this->assertStringContainsString('use HasQueryFlow;', $model);
    }

    public function test_make_controller_api_infers_the_model_from_the_name(): void
    {
        $this->make('make:controller', ['name' => 'WidgetController', '--api' => true]);

        $this->assertStringContainsString('Widget::query()', $this->generated('app/Http/Controllers/WidgetController.php'));
        $this->generated('app/Http/Requests/Widget/StoreRequest.php');
    }

    public function test_make_model_all_generates_the_full_stack_once(): void
    {
        $this->make('make:model', ['name' => 'Widget', '--all' => true]);

        $this->assertStringContainsString('IndexRequest $request', $this->generated('app/Http/Controllers/WidgetController.php'));
        $this->assertStringContainsString('use ChecksOwnership;', $this->generated('app/Policies/WidgetPolicy.php'));
        $this->generated('app/Http/Requests/Widget/UpdateRequest.php');
        $this->assertFileDoesNotExist(app_path('Http/Requests/StoreWidgetRequest.php'));
    }

    public function test_existing_files_are_never_overwritten(): void
    {
        File::ensureDirectoryExists(app_path('Http/Requests/Widget'));
        File::put(app_path('Http/Requests/Widget/StoreRequest.php'), '<?php // mine');

        $output = $this->make('make:controller', ['name' => 'WidgetController', '--model' => 'Widget']);

        $this->assertSame('<?php // mine', File::get(app_path('Http/Requests/Widget/StoreRequest.php')));
        $this->assertStringContainsString('already exists, skipped', $output);
    }

    public function test_plain_controllers_keep_laravel_behaviour(): void
    {
        $this->make('make:controller', ['name' => 'PingController', '--invokable' => true]);

        $this->assertStringContainsString('__invoke', $this->generated('app/Http/Controllers/PingController.php'));
        $this->assertFileDoesNotExist(app_path('Http/Resources/PingResource.php'));
    }

    /**
     * Runs a generator the way `php artisan ... --no-interaction` does (prompts take their default).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function make(string $command, array $arguments): string
    {
        $this->assertSame(0, Artisan::call($command, [...$arguments, '--no-interaction' => true]));

        return Artisan::output();
    }

    /**
     * Asserts that a generated file exists, has no leftover placeholders and is valid PHP.
     */
    private function generated(string $path): string
    {
        $file = base_path($path);
        $this->assertFileExists($file);

        $content = File::get($file);
        $this->assertStringNotContainsString('{{', $content, "{$path} has unreplaced placeholders");

        exec('php -l '.escapeshellarg($file), $output, $code);
        $this->assertSame(0, $code, implode("\n", $output));

        return $content;
    }

    private function cleanUp(): void
    {
        foreach (self::GENERATED as $path) {
            $path = base_path($path);
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        File::delete(File::glob(database_path('migrations/*_create_widgets_table.php')));
    }
}
