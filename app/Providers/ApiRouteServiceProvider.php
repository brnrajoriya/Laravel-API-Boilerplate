<?php

namespace App\Providers;

use Illuminate\Routing\PendingResourceRegistration;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registers the `Route::apiCrud()` macro:
 *
 *   Route::apiCrud('posts', PostController::class);
 *
 *   GET     posts                index
 *   POST    posts                store
 *   DELETE  posts                bulkDestroy   (when the controller has it)
 *   GET     posts/{post}         show
 *   PUT     posts/{post}         update
 *   DELETE  posts/{post}         destroy
 *   POST    posts/{post}/restore restore       (when the controller has it; finds trashed records)
 */
class ApiRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::macro('apiCrud', function (string $name, string $controller): PendingResourceRegistration {
            $parameter = Str::singular(str_replace('-', '_', $name));

            if (method_exists($controller, 'bulkDestroy')) {
                Route::delete($name, [$controller, 'bulkDestroy'])->name("{$name}.bulk-destroy");
            }

            if (method_exists($controller, 'restore')) {
                Route::post("{$name}/{{$parameter}}/restore", [$controller, 'restore'])
                    ->name("{$name}.restore")
                    ->withTrashed();
            }

            return Route::apiResource($name, $controller);
        });
    }
}
