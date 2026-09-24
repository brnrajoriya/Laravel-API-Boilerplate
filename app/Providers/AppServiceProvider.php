<?php

namespace App\Providers;

use App\Console\Commands\ApiControllerMakeCommand;
use App\Console\Commands\ApiModelMakeCommand;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Http\Request;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // `make:controller` / `make:model` generate the boilerplate's API controller + form requests.
        $this->app->extend(ControllerMakeCommand::class, fn ($command, $app) => new ApiControllerMakeCommand($app['files']));
        $this->app->extend(ModelMakeCommand::class, fn ($command, $app) => new ApiModelMakeCommand($app['files']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $production = $this->app->isProduction();

        // Catch N+1 queries, mass-assignment typos and missing attributes while developing.
        Model::shouldBeStrict(! $production);

        // Refuse `migrate:fresh`, `db:wipe`, ... on production.
        DB::prohibitDestructiveCommands($production);

        // Immutable dates avoid accidental mutation bugs.
        Date::use(CarbonImmutable::class);

        if ($production) {
            URL::forceHttps();
        }

        Password::defaults(fn () => $production
            ? Password::min(8)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8));

        // Password reset links point to the frontend (e.g. the Angular boilerplate).
        ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim((string) config('app.frontend_url'), '/')
            .'/reset-password/'.$token.'?email='.urlencode($user->getEmailForPasswordReset()));

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) config('api.rate_limit.api'))
            ->by($request->user()?->id ?: $request->ip()));

        // Login / register / password reset: slow down brute force per email + IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute((int) config('api.rate_limit.auth'))
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute((int) config('api.rate_limit.uploads'))
            ->by($request->user()?->id ?: $request->ip()));
    }
}
