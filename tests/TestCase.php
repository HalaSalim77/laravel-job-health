<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Tests;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Yaaqen\JobHealth\Facades\JobHealth as JobHealthFacade;
use Yaaqen\JobHealth\JobHealthServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            JobHealthServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'JobHealth' => JobHealthFacade::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', static fn (): string => 'login')->name('login');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('job-health.enabled', true);
        $app['config']->set('job-health.path', 'job-health');
        $app['config']->set('job-health.middleware', ['web', 'auth']);
        $app['config']->set('job-health.history_days', 30);
        $app['config']->set('job-health.counter_timezone', 'UTC');
        $app['config']->set('job-health.prune_days', 30);
        $app['config']->set('job-health.missed.grace_minutes', 30);
        $app['config']->set('job-health.missed.stale_intervals', 2);
        $app['config']->set('job-health.groups', [
            'general' => 'General',
            'backup' => 'Backups',
        ]);
        $app['config']->set('job-health.jobs', $this->defaultJobs());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function defaultJobs(): array
    {
        return [
            'sitemap' => [
                'name' => 'Generate sitemap',
                'group' => 'general',
                'cron' => '0 5 * * *',
                'timezone' => 'UTC',
                'schedule_label' => 'Daily 05:00',
                'record_schedule_events' => true,
                'meta' => ['tracks_counts' => false],
            ],
            'prices' => [
                'name' => 'Sync prices',
                'group' => 'general',
                'cron' => '0 * * * *',
                'timezone' => 'UTC',
                'schedule_label' => 'Hourly',
                'record_schedule_events' => true,
                'meta' => ['tracks_counts' => true],
            ],
            'monthly-report' => [
                'name' => 'Monthly report',
                'group' => 'backup',
                'cron' => '0 0 1 * *',
                'timezone' => 'UTC',
                'schedule_label' => 'Monthly',
                'record_schedule_events' => false,
                'meta' => [],
            ],
        ];
    }
}

final class User extends Authenticatable
{
    protected $guarded = [];

    protected $table = 'users';
}
