<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;
use Yaaqen\JobHealth\Data\DashboardData;
use Yaaqen\JobHealth\Data\JobCardData;
use Yaaqen\JobHealth\Data\JobDefinition;
use Yaaqen\JobHealth\Data\JobGroupData;
use Yaaqen\JobHealth\Data\LogEntryData;
use Yaaqen\JobHealth\Enums\HealthStatus;
use Yaaqen\JobHealth\Enums\RunSource;
use Yaaqen\JobHealth\Enums\RunStatus;
use Yaaqen\JobHealth\Models\JobRun;
use Yaaqen\JobHealth\Resolvers\HistoryBarResolver;
use Yaaqen\JobHealth\Resolvers\MissedRunResolver;
use Yaaqen\JobHealth\Support\Catalog;
use Yaaqen\JobHealth\Support\CronSchedule;

final class JobHealthService
{
    private const LOG_LIMIT = 50;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Repository $config,
        private readonly MissedRunResolver $missedRunResolver,
        private readonly HistoryBarResolver $historyBarResolver,
        private readonly CronSchedule $cronSchedule,
        private readonly Translator $translator,
    ) {}

    public function dashboard(?CarbonInterface $now = null): DashboardData
    {
        $now = CarbonImmutable::parse($now ?? now());
        $jobs = $this->catalog->jobs();
        $runsByKey = $this->runsFor(array_keys($jobs));

        $cards = [];

        foreach ($jobs as $job) {
            $runs = $runsByKey->get($job->key, collect());
            $cards[$job->key] = $this->card($job, $now, $runs);
        }

        return new DashboardData(
            counters: $this->counters($jobs, $cards, $now),
            groups: $this->groups($cards),
        );
    }

    /**
     * @param  Collection<int, JobRun>  $runs
     */
    private function card(JobDefinition $job, CarbonInterface $now, Collection $runs): JobCardData
    {
        $status = $this->missedRunResolver->resolve($job, $now);
        $lastRun = $runs->first();
        $countsRun = $runs->first(fn (JobRun $run): bool => $run->hasCounts());

        $missedExpectedAt = null;

        if ($status === HealthStatus::Missed && $runs->isEmpty()) {
            $expected = $this->missedRunResolver->expectedAt($job, $now);
            $missedExpectedAt = $expected->timezone($job->timezone)->format('H:i T');
        }

        $countsLabel = null;

        if ($countsRun !== null && ($job->tracksCounts() || $countsRun->hasCounts())) {
            $countsLabel = $this->countsLabel(
                (int) ($countsRun->success_count ?? 0),
                (int) ($countsRun->failed_count ?? 0),
            );
        }

        return new JobCardData(
            key: $job->key,
            name: $job->name,
            group: $job->group,
            scheduleLabel: $job->scheduleLabel,
            lastRunHuman: $this->lastRunHuman($lastRun, $now),
            status: $status,
            history: $this->historyBarResolver->resolve($job, $now, $runs),
            logs: $this->logs($runs, $job),
            missedExpectedAt: $missedExpectedAt,
            countsLabel: $countsLabel,
        );
    }

    /**
     * @param  array<string, JobDefinition>  $jobs
     * @param  array<string, JobCardData>  $cards
     * @return array{success: int, failed: int, missed: int}
     */
    private function counters(array $jobs, array $cards, CarbonInterface $now): array
    {
        $counterTimezone = (string) $this->config->get('job-health.counter_timezone', 'UTC');
        $today = CarbonImmutable::parse($now)->timezone($counterTimezone);

        $success = 0;
        $failed = 0;
        $missed = 0;

        foreach ($jobs as $job) {
            if (! $this->cronSchedule->wasDueOn($today, $job->cron, $job->timezone, $counterTimezone)) {
                continue;
            }

            match ($cards[$job->key]->status) {
                HealthStatus::Success => $success++,
                HealthStatus::Failed => $failed++,
                HealthStatus::Missed => $missed++,
                default => null,
            };
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'missed' => $missed,
        ];
    }

    /**
     * @param  array<string, JobCardData>  $cards
     * @return list<JobGroupData>
     */
    private function groups(array $cards): array
    {
        $groups = [];

        foreach ($this->catalog->grouped() as $group) {
            $jobs = [];

            foreach ($group['jobs'] as $definition) {
                $jobs[] = $cards[$definition->key];
            }

            if ($jobs === []) {
                continue;
            }

            $groups[] = new JobGroupData($group['key'], $group['label'], $jobs);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, JobRun>  $runs
     * @return list<LogEntryData>
     */
    private function logs(Collection $runs, JobDefinition $job): array
    {
        $dash = $this->translator->get('job-health::job-health.last_run_never');

        return $runs
            ->take(self::LOG_LIMIT)
            ->map(function (JobRun $run) use ($job, $dash): LogEntryData {
                $startedAt = $run->started_at?->timezone($job->timezone);

                return new LogEntryData(
                    time: $startedAt?->format('Y-m-d H:i') ?? $dash,
                    source: $this->sourceLabel($run->source),
                    actor: $run->triggered_by_name ?: $dash,
                    duration: $this->formatDuration($run->duration_ms),
                    status: $run->status->value,
                    extra: $run->hasCounts()
                        ? $this->countsLabel((int) ($run->success_count ?? 0), (int) ($run->failed_count ?? 0))
                        : null,
                    error: $run->error_message,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $keys
     * @return Collection<string, Collection<int, JobRun>>
     */
    private function runsFor(array $keys): Collection
    {
        if ($keys === []) {
            return collect();
        }

        return JobRun::query()
            ->whereIn('job_key', $keys)
            ->orderByDesc('started_at')
            ->get()
            ->groupBy('job_key');
    }

    private function lastRunHuman(?JobRun $run, CarbonInterface $now): string
    {
        if ($run?->started_at === null) {
            return $this->translator->get('job-health::job-health.last_run_never');
        }

        return $run->started_at->diffForHumans($now);
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            RunSource::Schedule->value => $this->translator->get('job-health::job-health.source_schedule'),
            RunSource::Manual->value => $this->translator->get('job-health::job-health.source_manual'),
            default => $this->translator->get('job-health::job-health.source_custom'),
        };
    }

    private function countsLabel(int $ok, int $fail): string
    {
        return $this->translator->get('job-health::job-health.counts', [
            'ok' => $ok,
            'fail' => $fail,
            'total' => $ok + $fail,
        ]);
    }

    private function formatDuration(?int $durationMs): string
    {
        if ($durationMs === null) {
            return $this->translator->get('job-health::job-health.last_run_never');
        }

        if ($durationMs < 1000) {
            return $durationMs.'ms';
        }

        $seconds = $durationMs / 1000;

        if ($seconds < 60) {
            return number_format($seconds, $seconds >= 10 ? 0 : 1).'s';
        }

        $minutes = intdiv((int) round($seconds), 60);
        $remain = ((int) round($seconds)) % 60;

        return $minutes.'m '.str_pad((string) $remain, 2, '0', STR_PAD_LEFT).'s';
    }
}
