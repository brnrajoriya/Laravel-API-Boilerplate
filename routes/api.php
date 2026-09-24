<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DummyController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 - every route is prefixed with /api/v1
|--------------------------------------------------------------------------
| All routes already run the `api` middleware group (JSON, throttle:api).
*/

Route::prefix('v1')->name('v1.')->group(function (): void {

    // Public auth endpoints (stricter rate limit against brute force).
    Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function (): void {
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('register', 'register')->name('register');
            Route::post('login', 'login')->name('login');
            Route::post('forgot-password', 'forgotPassword')->name('forgot-password');
            Route::post('reset-password', 'resetPassword')->name('reset-password');
        });

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', 'me')->name('me');
            Route::put('me', 'updateProfile')->name('update-profile');
            Route::put('password', 'changePassword')->name('change-password');
            Route::post('logout', 'logout')->name('logout');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::apiResource('uploads', UploadController::class)->only(['index', 'show', 'destroy']);
        Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:uploads')->name('uploads.store');

        // Example resource generated with `php artisan make:model Dummy -a`.
        Route::apiResource('dummies', DummyController::class);
    });
});
