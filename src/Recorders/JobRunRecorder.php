<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Recorders;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Yaaqen\JobHealth\Enums\RunSource;
use Yaaqen\JobHealth\Enums\RunStatus;
use Yaaqen\JobHealth\Models\JobRun;

final class JobRunRecorder
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function start(
        string $key,
        RunSource|string $source,
        ?int $userId = null,
        ?string $userName = null,
        ?array $meta = null,
        ?CarbonInterface $startedAt = null,
    ): ?JobRun {
        if (! $this->isKnown($key)) {
            return null;
        }

        return JobRun::query()->create([
            'job_key' => $key,
            'source' => $this->sourceValue($source),
            'triggered_by' => $userId,
            'triggered_by_name' => $userName,
            'status' => RunStatus::Running,
            'meta' => $meta,
            'started_at' => $startedAt ?? now(),
        ]);
    }

    public function succeed(JobRun $run, ?int $ok = null, ?int $fail = null): JobRun
    {
        $finishedAt = now();

        $run->success_count = $ok;
        $run->failed_count = $fail;
        $run->finished_at = $finishedAt;
        $run->duration_ms ??= $this->durationMs($run, $finishedAt);

        if (($fail ?? 0) > 0) {
            $run->status = RunStatus::Failed;
            $run->error_message = $this->countsLabel($ok ?? 0, $fail);
        } else {
            $run->status = RunStatus::Success;
        }

        $run->save();

        return $run;
    }

    public function fail(JobRun $run, string $message, ?int $ok = null, ?int $fail = null): JobRun
    {
        $finishedAt = now();

        $run->status = RunStatus::Failed;
        $run->error_message = $message;
        $run->success_count = $ok;
        $run->failed_count = $fail;
        $run->finished_at = $finishedAt;
        $run->duration_ms ??= $this->durationMs($run, $finishedAt);
        $run->save();

        return $run;
    }

    public function isKnown(string $key): bool
    {
        return array_key_exists($key, $this->config->get('job-health.jobs', []));
    }

    private function sourceValue(RunSource|string $source): string
    {
        return $source instanceof RunSource ? $source->value : $source;
    }

    private function durationMs(JobRun $run, CarbonInterface $finishedAt): int
    {
        if ($run->started_at === null) {
            return 0;
        }

        return (int) abs($run->started_at->diffInMilliseconds($finishedAt, true));
    }

    private function countsLabel(int $ok, int $fail): string
    {
        return trans('job-health::job-health.counts', [
            'ok' => $ok,
            'fail' => $fail,
            'total' => $ok + $fail,
        ]);
    }
}
