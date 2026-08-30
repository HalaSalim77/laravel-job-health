<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Yaaqen\JobHealth\Http\Controllers\DashboardController;
use Yaaqen\JobHealth\Listeners\RecordScheduledTask;

final class JobHealthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/job-health.php', 'job-health');

        $this->app->singleton(JobHealth::class);
        $this->app->singleton(RecordScheduledTask::class);
        $this->app->alias(JobHealth::class, 'job-health');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'job-health');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'job-health');

        $this->publishes([
            __DIR__.'/../config/job-health.php' => config_path('job-health.php'),
        ], 'job-health-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/job-health'),
        ], 'job-health-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/job-health'),
        ], 'job-health-lang');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'job-health-migrations');

        if ($this->app['config']->get('job-health.enabled', true)) {
            $this->registerScheduleHooks();
            $this->registerRoutes();
        }
    }

    private function registerScheduleHooks(): void
    {
        $app = $this->app;

        $app->make(Dispatcher::class)->listen(
            ScheduledTaskFailed::class,
            [RecordScheduledTask::class, 'handleFailed'],
        );

        Artisan::starting(function () use ($app): void {
            $hook = $app->make(RecordScheduledTask::class);

            foreach ($app->make(Schedule::class)->events() as $event) {
                $hook->hook($event);
            }
        });
    }

    private function registerRoutes(): void
    {
        Route::middleware($this->app['config']->get('job-health.middleware', ['web', 'auth']))
            ->prefix($this->app['config']->get('job-health.path', 'job-health'))
            ->group(function (): void {
                Route::get('/', [DashboardController::class, 'index'])->name('job-health.dashboard');
                Route::get('/api', [DashboardController::class, 'api'])->name('job-health.api');
            });
    }
}
