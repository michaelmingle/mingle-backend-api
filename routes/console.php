<?php

use App\Console\Commands\SendEventReminders;
use App\Console\Commands\SendNetworkingSuggestions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled notifications
|--------------------------------------------------------------------------
| Requires a real cron entry (`* * * * * php artisan schedule:run`) or
| `php artisan schedule:work` in dev to actually fire -- see the README.
| Both commands are no-ops with respect to push delivery unless FCM is
| configured (config/mingle.php); they still write to the notifications
| table either way, so the in-app bell always works even without push.
*/
Schedule::command(SendEventReminders::class)
    ->hourly()
    ->withoutOverlapping();

Schedule::command(SendNetworkingSuggestions::class)
    ->dailyAt('09:00')
    ->withoutOverlapping();
