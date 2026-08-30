<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Yaaqen\JobHealth\Support\CronSchedule;

it('reports due days for a monthly cron and grey days otherwise', function (): void {
    $cron = new CronSchedule;
    $expression = '0 0 1 * *';

    expect($cron->wasDueOn(CarbonImmutable::parse('2026-08-01', 'UTC'), $expression, 'UTC', 'UTC'))->toBeTrue()
        ->and($cron->wasDueOn(CarbonImmutable::parse('2026-08-15', 'UTC'), $expression, 'UTC', 'UTC'))->toBeFalse()
        ->and($cron->wasDueOn(CarbonImmutable::parse('2026-08-30', 'UTC'), $expression, 'UTC', 'UTC'))->toBeFalse();
});

it('returns previous and nth previous run dates', function (): void {
    $cron = new CronSchedule;
    $now = CarbonImmutable::parse('2026-08-30 12:00:00', 'UTC');

    expect($cron->previousRun($now, '0 * * * *', 'UTC')->format('Y-m-d H:i'))->toBe('2026-08-30 12:00')
        ->and($cron->nthPreviousRun($now, '0 * * * *', 'UTC', 1)->format('Y-m-d H:i'))->toBe('2026-08-30 11:00')
        ->and($cron->nthPreviousRun($now, '0 5 * * *', 'UTC', 1)->format('Y-m-d H:i'))->toBe('2026-08-29 05:00');
});
