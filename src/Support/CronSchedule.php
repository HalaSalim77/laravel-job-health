<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use DateTimeInterface;

final class CronSchedule
{
    public function previousRun(CarbonInterface $now, string $expression, string $timezone): CarbonImmutable
    {
        return $this->nthPreviousRun($now, $expression, $timezone, 0);
    }

    public function nthPreviousRun(CarbonInterface $now, string $expression, string $timezone, int $nth): CarbonImmutable
    {
        $cron = new CronExpression($expression);
        $current = CarbonImmutable::parse($now)->timezone($timezone);

        $date = $cron->getPreviousRunDate($current, $nth, true, $timezone);

        return $this->asImmutable($date, $timezone);
    }

    public function nextRun(CarbonInterface $now, string $expression, string $timezone): CarbonImmutable
    {
        $cron = new CronExpression($expression);
        $current = CarbonImmutable::parse($now)->timezone($timezone);

        $date = $cron->getNextRunDate($current, 0, false, $timezone);

        return $this->asImmutable($date, $timezone);
    }

    public function wasDueOn(
        CarbonInterface $day,
        string $expression,
        string $jobTimezone,
        string $dayTimezone,
    ): bool {
        $cron = new CronExpression($expression);
        $start = CarbonImmutable::parse($day->toDateString(), $dayTimezone)->startOfDay();
        $end = $start->endOfDay();
        $from = $start->subSecond();

        $next = $this->asImmutable(
            $cron->getNextRunDate($from, 0, false, $jobTimezone),
            $jobTimezone,
        );

        return $next->lte($end);
    }

    private function asImmutable(DateTimeInterface $date, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->timezone($timezone);
    }
}
