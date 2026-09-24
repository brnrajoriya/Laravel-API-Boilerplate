<?php

use Illuminate\Support\Facades\Schedule;

// Housekeeping - run `php artisan schedule:work` locally, or `schedule:run` from cron every minute.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
