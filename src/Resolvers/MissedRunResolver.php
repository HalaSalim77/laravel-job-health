<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Resolvers;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Yaaqen\JobHealth\Data\JobDefinition;
use Yaaqen\JobHealth\Enums\HealthStatus;
use Yaaqen\JobHealth\Enums\RunStatus;
use Yaaqen\JobHealth\Models\JobRun;
use Yaaqen\JobHealth\Support\CronSchedule;

final class MissedRunResolver
{
    public function __construct(
        private readonly Repository $config,
        private readonly CronSchedule $cronSchedule,
    ) {}

    public function resolve(JobDefinition $job, CarbonInterface $now): HealthStatus
    {
        $expected = $this->expectedAt($job, $now);
        $graceMinutes = (int) $this->config->get('job-health.missed.grace_minutes', 30);
        $staleIntervals = (int) $this->config->get('job-health.missed.stale_intervals', 2);

        $windowStart = $expected->subMinute();
        $windowEnd = $expected->addMinutes($graceMinutes);

        $hasRunInWindow = JobRun::query()
            ->where('job_key', $job->key)
            ->where('started_at', '>=', $windowStart)
            ->where('started_at', '<=', $windowEnd)
            ->exists();

        $missedByGrace = $now->gte($expected->addMinutes($graceMinutes)) && ! $hasRunInWindow;
        $missedByStale = $this->isStale($job, $now, $staleIntervals);

        if ($missedByGrace || $missedByStale) {
            return HealthStatus::Missed;
        }

        if ($this->hasRunning($job->key)) {
            return HealthStatus::Running;
        }

        $lastFinished = $this->lastFinished($job->key);

        if ($lastFinished === null) {
            return HealthStatus::Never;
        }

        if ($lastFinished->status === RunStatus::Failed) {
            return HealthStatus::Failed;
        }

        return HealthStatus::Success;
    }

    public function expectedAt(JobDefinition $job, CarbonInterface $now): CarbonInterface
    {
        return $this->cronSchedule->previousRun($now, $job->cron, $job->timezone);
    }

    private function isStale(JobDefinition $job, CarbonInterface $now, int $staleIntervals): bool
    {
        if ($staleIntervals < 1) {
            return false;
        }

        $lastSuccess = JobRun::query()
            ->where('job_key', $job->key)
            ->where('status', RunStatus::Success)
            ->orderByDesc('started_at')
            ->first();

        if ($lastSuccess === null || $lastSuccess->started_at === null) {
            return false;
        }

        $stalePoint = $this->cronSchedule->nthPreviousRun(
            $now,
            $job->cron,
            $job->timezone,
            max(0, $staleIntervals - 1),
        );

        return $lastSuccess->started_at->lt($stalePoint);
    }

    private function hasRunning(string $key): bool
    {
        return JobRun::query()
            ->where('job_key', $key)
            ->where('status', RunStatus::Running)
            ->exists();
    }

    private function lastFinished(string $key): ?JobRun
    {
        return JobRun::query()
            ->where('job_key', $key)
            ->whereIn('status', [RunStatus::Success, RunStatus::Failed])
            ->orderByDesc('started_at')
            ->first();
    }
}
