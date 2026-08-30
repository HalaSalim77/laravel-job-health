<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Resolvers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Yaaqen\JobHealth\Data\HistoryDayData;
use Yaaqen\JobHealth\Data\JobDefinition;
use Yaaqen\JobHealth\Enums\RunStatus;
use Yaaqen\JobHealth\Models\JobRun;
use Yaaqen\JobHealth\Support\CronSchedule;

final class HistoryBarResolver
{
    public function __construct(
        private readonly Repository $config,
        private readonly CronSchedule $cronSchedule,
    ) {}

    /**
     * @param  Collection<int, JobRun>  $runs
     * @return list<HistoryDayData>
     */
    public function resolve(JobDefinition $job, CarbonInterface $now, Collection $runs): array
    {
        $days = (int) $this->config->get('job-health.history_days', 30);
        $counterTimezone = (string) $this->config->get('job-health.counter_timezone', 'UTC');
        $today = CarbonImmutable::parse($now)->timezone($counterTimezone)->startOfDay();

        $history = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $today->subDays($offset);
            $due = $this->cronSchedule->wasDueOn($day, $job->cron, $job->timezone, $counterTimezone);

            if (! $due) {
                $history[] = new HistoryDayData($day->toDateString(), 'none');

                continue;
            }

            $last = $this->lastRunOnDay($runs, $day, $counterTimezone);

            if ($last === null) {
                $history[] = new HistoryDayData($day->toDateString(), 'miss');

                continue;
            }

            $status = match ($last->status) {
                RunStatus::Success => 'ok',
                RunStatus::Failed => 'fail',
                RunStatus::Running => 'miss',
            };

            $history[] = new HistoryDayData(
                date: $day->toDateString(),
                status: $status,
                error: $last->status === RunStatus::Failed ? $last->error_message : null,
            );
        }

        return $history;
    }

    /**
     * @param  Collection<int, JobRun>  $runs
     */
    private function lastRunOnDay(Collection $runs, CarbonImmutable $day, string $timezone): ?JobRun
    {
        $start = $day->timezone($timezone)->startOfDay();
        $end = $start->endOfDay();

        return $runs
            ->filter(function (JobRun $run) use ($start, $end): bool {
                if ($run->started_at === null) {
                    return false;
                }

                return $run->started_at->between($start, $end);
            })
            ->sortByDesc(fn (JobRun $run): int => $run->started_at?->getTimestamp() ?? 0)
            ->first();
    }
}
