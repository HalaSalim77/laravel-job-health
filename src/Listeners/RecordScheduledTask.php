<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Config\Repository;
use WeakMap;
use Yaaqen\JobHealth\Enums\RunSource;
use Yaaqen\JobHealth\Recorders\JobRunRecorder;

final class RecordScheduledTask
{
    /** @var WeakMap<Event, true> */
    private WeakMap $hooked;

    /** @var WeakMap<Event, true> */
    private WeakMap $recorded;

    public function __construct(
        private readonly JobRunRecorder $recorder,
        private readonly Repository $config,
    ) {
        $this->hooked = new WeakMap;
        $this->recorded = new WeakMap;
    }

    public function hook(Event $event): void
    {
        if (isset($this->hooked[$event]) || ! $this->shouldRecord($event)) {
            return;
        }

        $this->hooked[$event] = true;

        $started = null;

        $event->before(function () use (&$started): void {
            $started = microtime(true);
        });

        $event->onSuccess(function () use ($event, &$started): void {
            $this->record($event, true, $this->durationMs($started), null);
        });

        $event->onFailure(function () use ($event, &$started): void {
            $exitCode = $event->exitCode ?? 1;

            $this->record($event, false, $this->durationMs($started), 'Exited with code '.$exitCode);
        });
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $this->record($event->task, false, null, $event->exception->getMessage());
    }

    public function shouldRecord(Event $event): bool
    {
        $key = $this->catalogKey($event);

        if ($key === null) {
            return false;
        }

        $job = $this->config->get('job-health.jobs.'.$key, []);

        return ($job['record_schedule_events'] ?? true) !== false;
    }

    public function catalogKey(Event $event): ?string
    {
        $jobs = $this->config->get('job-health.jobs', []);

        $candidates = [];

        if (isset($event->description) && is_string($event->description) && $event->description !== '') {
            $candidates[] = $event->description;
        }

        if (property_exists($event, 'name') && isset($event->name) && is_string($event->name) && $event->name !== '') {
            $candidates[] = $event->name;
        }

        if (method_exists($event, 'getSummaryForDisplay')) {
            $summary = $event->getSummaryForDisplay();

            if (is_string($summary) && $summary !== '') {
                $candidates[] = $summary;
            }
        }

        foreach (array_unique($candidates) as $candidate) {
            if (array_key_exists($candidate, $jobs)) {
                return $candidate;
            }
        }

        return null;
    }

    private function record(Event $task, bool $success, ?int $durationMs, ?string $error): void
    {
        if (isset($this->recorded[$task]) || ! $this->shouldRecord($task)) {
            return;
        }

        $this->recorded[$task] = true;

        $key = $this->catalogKey($task);

        if ($key === null) {
            return;
        }

        $startedAt = $durationMs !== null ? now()->subMilliseconds($durationMs) : now();

        $run = $this->recorder->start(
            $key,
            RunSource::Schedule,
            startedAt: $startedAt,
        );

        if ($run === null) {
            return;
        }

        if ($durationMs !== null) {
            $run->duration_ms = $durationMs;
        }

        if ($success) {
            $this->recorder->succeed($run);

            return;
        }

        $this->recorder->fail($run, $error ?? 'Scheduled task failed');
    }

    private function durationMs(mixed $started): ?int
    {
        if (! is_float($started) && ! is_int($started)) {
            return null;
        }

        return (int) max(0, round((microtime(true) - (float) $started) * 1000));
    }
}
