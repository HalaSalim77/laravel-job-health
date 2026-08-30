<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Data;

use JsonSerializable;

final readonly class JobGroupData implements JsonSerializable
{
    /**
     * @param  list<JobCardData>  $jobs
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $jobs,
    ) {}

    /**
     * @return array{key: string, label: string, jobs: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'jobs' => array_map(
                static fn (JobCardData $job): array => $job->toArray(),
                $this->jobs,
            ),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
