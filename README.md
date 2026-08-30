# yaaqen/laravel-job-health

Named-job catalog, run log, missed detection, and a 30-day history dashboard for Laravel 11 and 12.

This is **not** Horizon, Pulse, or a schedule runner. It answers one question: **did this named business job run when it was supposed to?**

A job here is a catalog entry (`key` + cron), not a PHP class. Only keys listed in config appear. Unknown `Schedule::` names are ignored. The dashboard is read-only — no retry, run, or dispatch actions.

## Install

Requires PHP 8.3+ and Laravel 11 or 12.

```bash
composer require yaaqen/laravel-job-health
```

Packagist: [yaaqen/laravel-job-health](https://packagist.org/packages/yaaqen/laravel-job-health)  
Source: [github.com/HalaSalim77/laravel-job-health](https://github.com/HalaSalim77/laravel-job-health)

To require from GitHub instead of Packagist:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/HalaSalim77/laravel-job-health"
    }
]
```

```bash
composer require yaaqen/laravel-job-health:^1.0
```

Publish what you need:

```bash
php artisan vendor:publish --tag=job-health-config
php artisan vendor:publish --tag=job-health-views
php artisan vendor:publish --tag=job-health-lang
php artisan vendor:publish --tag=job-health-migrations
```

Migrations are also loaded from the package, so `php artisan migrate` works without publishing.

## Catalog

Empty `jobs` = empty dashboard. Jobs are **not** auto-discovered from `app/Console/Kernel` or `routes/console.php`.

```php
// config/job-health.php
return [
    'enabled' => env('JOB_HEALTH_ENABLED', true),
    'path' => env('JOB_HEALTH_PATH', 'job-health'),
    'middleware' => ['web', 'auth'],
    'history_days' => 30,
    'counter_timezone' => 'UTC',
    'prune_days' => 30,
    'missed' => [
        'grace_minutes' => 30,
        'stale_intervals' => 2,
    ],
    'groups' => [
        'backup' => 'Backups',
        'general' => 'General',
    ],
    'jobs' => [
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
            'record_schedule_events' => false, // queued job records itself
            'meta' => ['tracks_counts' => true],
        ],
    ],
];
```

Name the scheduled task with the catalog key:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sitemap:generate')
    ->dailyAt('05:00')
    ->name('sitemap');
```

Do **not** point this at Horizon. Horizon shows workers. This package shows whether a named cadence actually ran.

## Recording

Schedule recording hooks `onSuccess` / `onFailure` on catalog events (runs on `schedule:run` **and** `schedule:test`) plus `ScheduledTaskFailed` if the command throws. Matching is by `->name('key')`. Skip with `record_schedule_events => false`.

For queued jobs that self-record, set `record_schedule_events` to `false` and call the fluent API from the job:

```php
use Yaaqen\JobHealth\Facades\JobHealth;

JobHealth::record('sitemap')->fromSchedule()->succeed();

JobHealth::record('prices')->manual(userId: 1, name: 'Ahmad')->succeed(ok: 1000, fail: 0);

JobHealth::record('calendar')->source('webhook')->fail('timeout');

$pending = JobHealth::record('backup')->fromSchedule();
$pending->start();
$pending->succeed(); // or $pending->fail('disk full')
```

Unknown keys are ignored — no stray `job_runs` rows.

If `fail > 0` on `succeed()`, the run is stored as **failed** and the card gets a counts label (`:ok/:total succeeded · :fail failed`).

## Dashboard

- `GET /job-health` — Blade UI (middleware from config, default `web` + `auth`)
- `GET /job-health/api` — same payload as JSON for apps that bring their own UI

No POST routes.

### Bring your own Blade

```blade
@php
    $dashboard = Http::withToken($token)->get(url('/job-health/api'))->json();
@endphp

@foreach ($dashboard['groups'] as $group)
    <h2>{{ $group['label'] }}</h2>
    @foreach ($group['jobs'] as $job)
        <p>{{ $job['name'] }} — {{ $job['status'] }}</p>
    @endforeach
@endforeach
```

Or inject `Yaaqen\JobHealth\Services\JobHealthService` and call `dashboard()`.

Path and middleware:

```php
'path' => env('JOB_HEALTH_PATH', 'job-health'),
'middleware' => ['web', 'auth'],
```

The default view is LTR. Wrap/publish `.job-health` and set `dir="rtl"` if you need RTL. Locales other than English (including `ar`) belong in the **app** via published lang files — this package ships `en` only.

## Missed detection

Per-job timezone. Expected time = previous cron occurrence.

A job is **missed** when:

1. `now >= expected + grace_minutes` and no run started in `[expected - 1 minute, expected + grace]`, or
2. last **success** is older than `stale_intervals` scheduled ticks (hourly + 2 intervals ≈ 2 hours, daily ≈ 2 days).

Other badges: `success`, `failed`, `running`, `never`.

Header counters are for **today** in `counter_timezone`: one count per catalog job that is due today (an hourly job that succeeded 24 times still counts as 1).

## Pruning

The package does **not** schedule `model:prune`. Add it in the app:

```php
use Illuminate\Support\Facades\Schedule;
use Yaaqen\JobHealth\Models\JobRun;

Schedule::command('model:prune', [
    '--model' => JobRun::class,
])->daily();
```

Rows older than `prune_days` (by `started_at`) are prunable.

## Tests

```bash
composer test
```

CI runs Pest on PHP 8.3/8.4 and Laravel 11/12.

## Gotchas

- Catalog `cron` must match the scheduler (`daily()` → `0 0 * * *`, `dailyAt('02:00')` → `0 2 * * *`).
- Job `timezone` must match `Schedule::…->timezone()`.
- `->name('sitemap')` must equal the catalog key. `schedule:test --name=` is the **artisan command** (`sitemap:generate`), not the key.
- Production needs `* * * * * php artisan schedule:run`. Without it, rows only appear from `schedule:test` / a manual `schedule:run`.
- Empty `jobs` = empty dashboard. Unknown `Schedule::` names are ignored.
- `JOB_HEALTH_ENABLED=false` disables routes and schedule hooks.
- Monthly jobs look missed until the 1st (or until you `schedule:test` them). That is expected.
