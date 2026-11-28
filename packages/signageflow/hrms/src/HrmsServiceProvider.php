<?php

namespace SignageFlow\Hrms;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SignageFlow\Hrms\Console\Commands\AccrueLeavesCommand;
use SignageFlow\Hrms\Console\Commands\BackfillAttendanceCommand;
use SignageFlow\Hrms\Console\Commands\DailyAttendanceRollupCommand;

class HrmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/hrms.php', 'hrms');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'hrms');

        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                DailyAttendanceRollupCommand::class,
                AccrueLeavesCommand::class,
                BackfillAttendanceCommand::class,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', 'auth', '2fa'])
                ->prefix(config('hrms.route_prefix', 'admin/hrms'))
                ->name('hrms.')
                ->group(__DIR__.'/../routes/web.php');

            Route::middleware(['web'])
                ->prefix(config('hrms.kiosk_prefix', 'hrms/kiosk'))
                ->name('hrms.kiosk.')
                ->group(__DIR__.'/../routes/kiosk.php');

            Route::middleware(['api', 'auth:sanctum'])
                ->prefix(config('hrms.mobile_api_prefix', 'api/mobile/hrms'))
                ->name('mobile.hrms.')
                ->group(__DIR__.'/../routes/api_mobile.php');
        }
    }
}
