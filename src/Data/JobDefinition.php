<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Data;

final readonly class JobDefinition
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $group,
        public string $cron,
        public string $timezone,
        public string $scheduleLabel,
        public bool $recordScheduleEvents,
        public array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            name: (string) ($config['name'] ?? $key),
            group: (string) ($config['group'] ?? 'general'),
            cron: (string) $config['cron'],
            timezone: (string) ($config['timezone'] ?? 'UTC'),
            scheduleLabel: (string) ($config['schedule_label'] ?? $config['cron']),
            recordScheduleEvents: (bool) ($config['record_schedule_events'] ?? true),
            meta: (array) ($config['meta'] ?? []),
        );
    }

    public function tracksCounts(): bool
    {
        return (bool) ($this->meta['tracks_counts'] ?? false);
    }
}
