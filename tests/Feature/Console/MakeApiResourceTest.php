<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakeApiResourceTest extends TestCase
{
    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        foreach (File::glob(database_path('migrations/*_create_widgets_table.php')) as $migration) {
            File::delete($migration);
        }

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMockingConsoleOutput();
    }

    public function test_make_controller_with_model_generates_api_controller_and_requests(): void
    {
        $this->track(app_path('Models/Widget.php'), app_path('Http/Controllers/Api/V1/WidgetController.php'), app_path('Http/Requests/Widget'));

        $output = $this->make('make:controller', ['name' => 'Api/V1/WidgetController', '--model' => 'Widget']);
        $this->assertStringContainsString("Route::apiResource('widgets', WidgetController::class)", $output);

        $controller = File::get(app_path('Http/Controllers/Api/V1/WidgetController.php'));
        $this->assertStringContainsString('namespace App\Http\Controllers\Api\V1;', $controller);
        $this->assertStringContainsString('use App\Http\Requests\Widget\IndexRequest;', $controller);
        $this->assertStringContainsString('@group Widgets', $controller);
        $this->assertStringContainsString('public function index(IndexRequest $request): JsonResponse', $controller);
        $this->assertStringContainsString('public function store(StoreRequest $request): JsonResponse', $controller);
        $this->assertStringContainsString('public function update(UpdateRequest $request, Widget $widget): JsonResponse', $controller);
        $this->assertStringNotContainsString('function create(', $controller);
        $this->assertStringNotContainsString('{{', $controller);

        foreach (['IndexRequest', 'StoreRequest', 'UpdateRequest'] as $request) {
            $content = File::get(app_path("Http/Requests/Widget/{$request}.php"));
            $this->assertStringContainsString('namespace App\Http\Requests\Widget;', $content);
            $this->assertStringNotContainsString('{{', $content);
        }

        $model = File::get(app_path('Models/Widget.php'));
        $this->assertStringContainsString('use HasApiQuery;', $model);

        // The generated files are valid PHP.
        foreach ([app_path('Http/Controllers/Api/V1/WidgetController.php'), app_path('Models/Widget.php')] as $file) {
            exec('php -l '.escapeshellarg($file), $output, $code);
            $this->assertSame(0, $code, implode("\n", $output));
        }
    }

    public function test_make_controller_api_infers_the_model_from_the_name(): void
    {
        $this->track(app_path('Models/Widget.php'), app_path('Http/Controllers/WidgetController.php'), app_path('Http/Requests/Widget'));

        $this->make('make:controller', ['name' => 'WidgetController', '--api' => true]);

        $this->assertStringContainsString('Widget::query()', File::get(app_path('Http/Controllers/WidgetController.php')));
        $this->assertFileExists(app_path('Http/Requests/Widget/StoreRequest.php'));
    }

    public function test_make_model_all_generates_the_full_stack(): void
    {
        $this->track(
            app_path('Models/Widget.php'),
            app_path('Http/Controllers/WidgetController.php'),
            app_path('Http/Requests/Widget'),
            app_path('Policies/WidgetPolicy.php'),
            database_path('factories/WidgetFactory.php'),
            database_path('seeders/WidgetSeeder.php'),
        );

        $this->make('make:model', ['name' => 'Widget', '--all' => true]);

        $this->assertStringContainsString('IndexRequest $request', File::get(app_path('Http/Controllers/WidgetController.php')));
        $this->assertFileExists(app_path('Http/Requests/Widget/UpdateRequest.php'));
        $this->assertFileDoesNotExist(app_path('Http/Requests/StoreWidgetRequest.php'));
    }

    public function test_plain_controllers_keep_laravel_behaviour(): void
    {
        $this->track(app_path('Http/Controllers/PingController.php'));

        $this->make('make:controller', ['name' => 'PingController', '--invokable' => true]);

        $this->assertStringContainsString('__invoke', File::get(app_path('Http/Controllers/PingController.php')));
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

    private function track(string ...$paths): void
    {
        array_push($this->created, ...$paths);
    }
}
