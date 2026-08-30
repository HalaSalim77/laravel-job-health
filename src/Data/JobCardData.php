<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Data;

use JsonSerializable;
use Yaaqen\JobHealth\Enums\HealthStatus;

final readonly class JobCardData implements JsonSerializable
{
    /**
     * @param  list<HistoryDayData>  $history
     * @param  list<LogEntryData>  $logs
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $group,
        public string $scheduleLabel,
        public string $lastRunHuman,
        public HealthStatus $status,
        public array $history,
        public array $logs,
        public ?string $missedExpectedAt = null,
        public ?string $countsLabel = null,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     group: string,
     *     schedule_label: string,
     *     last_run_human: string,
     *     status: string,
     *     history: list<array<string, string>>,
     *     logs: list<array<string, string>>,
     *     missed_expected_at: string|null,
     *     counts_label: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'group' => $this->group,
            'schedule_label' => $this->scheduleLabel,
            'last_run_human' => $this->lastRunHuman,
            'status' => $this->status->value,
            'history' => array_map(
                static fn (HistoryDayData $day): array => $day->toArray(),
                $this->history,
            ),
            'logs' => array_map(
                static fn (LogEntryData $log): array => $log->toArray(),
                $this->logs,
            ),
            'missed_expected_at' => $this->missedExpectedAt,
            'counts_label' => $this->countsLabel,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
