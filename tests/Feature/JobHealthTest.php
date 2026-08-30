<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\EventMutex;
use Illuminate\Support\Facades\Route;
use Yaaqen\JobHealth\Enums\HealthStatus;
use Yaaqen\JobHealth\Enums\RunStatus;
use Yaaqen\JobHealth\Facades\JobHealth;
use Yaaqen\JobHealth\Listeners\RecordScheduledTask;
use Yaaqen\JobHealth\Models\JobRun;
use Yaaqen\JobHealth\Services\JobHealthService;
use Yaaqen\JobHealth\Tests\User;

function makeUser(): User
{
    $user = new User;
    $user->forceFill([
        'id' => 1,
        'name' => 'Tester',
        'email' => 'tester@example.com',
        'password' => 'secret',
    ]);

    return $user;
}

function makeScheduledEvent(string $command, ?string $name = null, ?int $exitCode = 0): Event
{
    $mutex = Mockery::mock(EventMutex::class);
    $task = new Event($mutex, $command);

    if ($name !== null) {
        $task->name($name);
    }

    $task->exitCode = $exitCode;

    return $task;
}

function createRun(string $key, array $overrides = []): JobRun
{
    return JobRun::query()->create(array_merge([
        'job_key' => $key,
        'source' => 'schedule',
        'status' => RunStatus::Success,
        'started_at' => now(),
        'finished_at' => now(),
        'duration_ms' => 100,
    ], $overrides));
}

function card(string $key): object
{
    $dashboard = app(JobHealthService::class)->dashboard();

    foreach ($dashboard->groups as $group) {
        foreach ($group->jobs as $job) {
            if ($job->key === $key) {
                return $job;
            }
        }
    }

    throw new RuntimeException("Job card [{$key}] not found");
}

it('ignores recorder calls for unknown keys', function (): void {
    JobHealth::record('unknown')->fromSchedule()->succeed();
    JobHealth::record('not-in-catalog')->manual(userId: 1, name: 'Ahmad')->fail('nope');

    expect(JobRun::query()->count())->toBe(0);
});

it('records known keys via the fluent api', function (): void {
    JobHealth::record('sitemap')->fromSchedule()->succeed();
    JobHealth::record('calendar')->source('webhook')->fail('timeout');

    expect(JobRun::query()->count())->toBe(1);

    $run = JobRun::query()->first();
    expect($run->job_key)->toBe('sitemap')
        ->and($run->source)->toBe('schedule')
        ->and($run->status)->toBe(RunStatus::Success);
});

it('marks succeed with fail count greater than zero as failed and exposes counts_label', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    JobHealth::record('prices')->manual(userId: 1, name: 'Ahmad')->succeed(ok: 1000, fail: 3);

    $run = JobRun::query()->first();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->success_count)->toBe(1000)
        ->and($run->failed_count)->toBe(3)
        ->and($run->error_message)->toBe('1000/1003 succeeded · 3 failed');

    expect(card('prices')->countsLabel)->toBe('1000/1003 succeeded · 3 failed');
});

it('records named scheduled tasks via after callbacks and skips unnamed or self-recording jobs', function (): void {
    $hook = app(RecordScheduledTask::class);

    $named = makeScheduledEvent('inspire', 'sitemap', 0);
    $hook->hook($named);
    $named->callBeforeCallbacks(app());
    $named->callAfterCallbacks(app());

    expect(JobRun::query()->count())->toBe(1);

    $run = JobRun::query()->first();
    expect($run->job_key)->toBe('sitemap')
        ->and($run->source)->toBe('schedule')
        ->and($run->status)->toBe(RunStatus::Success)
        ->and($run->triggered_by_name)->toBeNull();

    JobRun::query()->delete();

    $unnamed = makeScheduledEvent('inspire', exitCode: 0);
    $hook->hook($unnamed);
    $unnamed->callAfterCallbacks(app());
    expect(JobRun::query()->count())->toBe(0);

    $selfRecording = makeScheduledEvent('report:monthly', 'monthly-report', 0);
    $hook->hook($selfRecording);
    $selfRecording->callAfterCallbacks(app());
    expect(JobRun::query()->count())->toBe(0);

    $failed = makeScheduledEvent('sitemap:generate', 'sitemap', 1);
    $hook->hook($failed);
    $failed->callAfterCallbacks(app());

    expect(JobRun::query()->count())->toBe(1)
        ->and(JobRun::query()->first()->status)->toBe(RunStatus::Failed)
        ->and(JobRun::query()->first()->error_message)->toBe('Exited with code 1');

    $hook->hook($failed);
    $failed->callAfterCallbacks(app());
    event(new ScheduledTaskFailed($failed, new RuntimeException('boom')));
    expect(JobRun::query()->count())->toBe(1);

    event(new ScheduledTaskFinished(makeScheduledEvent('inspire', 'sitemap'), 1.25));
    expect(JobRun::query()->count())->toBe(1);

    event(new ScheduledTaskFailed(
        makeScheduledEvent('sitemap:generate', 'sitemap'),
        new RuntimeException('boom'),
    ));

    expect(JobRun::query()->count())->toBe(2)
        ->and(JobRun::query()->orderByDesc('id')->first()->error_message)->toBe('boom');
});

it('marks a job missed when no run arrives within grace of the expected time', function (): void {
    Carbon::setTestNow('2026-08-30 05:31:00');

    expect(card('sitemap')->status)->toBe(HealthStatus::Missed)
        ->and(card('sitemap')->missedExpectedAt)->toBe('05:00 UTC');
});

it('does not mark a job missed when a run arrives inside the grace window', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    createRun('sitemap', [
        'started_at' => Carbon::parse('2026-08-30 05:10:00'),
        'finished_at' => Carbon::parse('2026-08-30 05:11:00'),
    ]);

    expect(card('sitemap')->status)->toBe(HealthStatus::Success);
});

it('marks a job missed when the last success is older than two hourly intervals', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    createRun('prices', [
        'started_at' => Carbon::parse('2026-08-30 10:00:00'),
        'finished_at' => Carbon::parse('2026-08-30 10:00:05'),
    ]);

    expect(card('prices')->status)->toBe(HealthStatus::Missed);
});

it('marks a job missed when the last success is older than two daily intervals', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    createRun('sitemap', [
        'started_at' => Carbon::parse('2026-08-28 05:00:00'),
        'finished_at' => Carbon::parse('2026-08-28 05:00:08'),
    ]);

    expect(card('sitemap')->status)->toBe(HealthStatus::Missed);
});

it('uses grey history days when cron is not due', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    $history = card('monthly-report')->history;
    $byDate = [];

    foreach ($history as $day) {
        $byDate[$day->date] = $day->status;
    }

    expect($history)->toHaveCount(30)
        ->and($byDate['2026-08-01'])->toBe('miss')
        ->and($byDate['2026-08-15'])->toBe('none')
        ->and($byDate['2026-08-30'])->toBe('none')
        ->and(array_values(array_unique($byDate)))->each->toBeIn(['none', 'miss']);
});

it('ignores jobs that are not due today in the header counters', function (): void {
    Carbon::setTestNow('2026-08-30 12:31:00');

    $dashboard = app(JobHealthService::class)->dashboard();

    expect($dashboard->counters['missed'])->toBe(2)
        ->and($dashboard->counters['success'])->toBe(0)
        ->and($dashboard->counters['failed'])->toBe(0);
});

it('counts jobs not raw run rows in the today counters', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    foreach (range(0, 23) as $hour) {
        createRun('prices', [
            'started_at' => Carbon::parse(sprintf('2026-08-30 %02d:00:00', $hour)),
            'finished_at' => Carbon::parse(sprintf('2026-08-30 %02d:00:02', $hour)),
        ]);
    }

    createRun('sitemap', [
        'started_at' => Carbon::parse('2026-08-30 05:00:00'),
        'finished_at' => Carbon::parse('2026-08-30 05:00:04'),
    ]);

    $dashboard = app(JobHealthService::class)->dashboard();

    expect($dashboard->counters['success'])->toBe(2)
        ->and($dashboard->counters['failed'])->toBe(0)
        ->and($dashboard->counters['missed'])->toBe(0)
        ->and(JobRun::query()->count())->toBe(25);
});

it('prunes runs older than prune_days', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    createRun('sitemap', [
        'started_at' => now()->subDays(31),
        'finished_at' => now()->subDays(31),
    ]);
    createRun('sitemap', [
        'started_at' => now()->subDays(10),
        'finished_at' => now()->subDays(10),
    ]);

    $prunable = (new JobRun)->prunable()->get();

    expect($prunable)->toHaveCount(1)
        ->and($prunable->first()->started_at->toDateString())->toBe('2026-07-30');
});

it('returns a stable dashboard json shape', function (): void {
    Carbon::setTestNow('2026-08-30 12:00:00');

    $this->actingAs(makeUser())
        ->getJson('/job-health/api')
        ->assertOk()
        ->assertJsonStructure([
            'counters' => ['success', 'failed', 'missed'],
            'groups' => [
                [
                    'key',
                    'label',
                    'jobs' => [
                        [
                            'key',
                            'name',
                            'group',
                            'schedule_label',
                            'last_run_human',
                            'status',
                            'history' => [
                                ['date', 'status'],
                            ],
                            'logs',
                            'missed_expected_at',
                            'counts_label',
                        ],
                    ],
                ],
            ],
        ]);
});

it('returns the dashboard with auth middleware', function (): void {
    $this->get('/job-health')->assertRedirect('/login');

    $this->actingAs(makeUser())
        ->get('/job-health')
        ->assertOk()
        ->assertSee('Job Health')
        ->assertSee('Generate sitemap')
        ->assertDontSee('<form', false)
        ->assertDontSee('<button', false);
});

it('does not register post routes', function (): void {
    $posts = collect(Route::getRoutes())
        ->filter(function ($route): bool {
            return in_array('POST', $route->methods(), true)
                && str_contains($route->uri(), 'job-health');
        });

    expect($posts)->toBeEmpty();

    $this->actingAs(makeUser())->post('/job-health')->assertMethodNotAllowed();
});
