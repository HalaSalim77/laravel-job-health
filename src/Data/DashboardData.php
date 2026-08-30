<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Data;

use JsonSerializable;

final readonly class DashboardData implements JsonSerializable
{
    /**
     * @param  array{success: int, failed: int, missed: int}  $counters
     * @param  list<JobGroupData>  $groups
     */
    public function __construct(
        public array $counters,
        public array $groups,
    ) {}

    /**
     * @return array{
     *     counters: array{success: int, failed: int, missed: int},
     *     groups: list<array{key: string, label: string, jobs: list<array<string, mixed>>}>
     * }
     */
    public function toArray(): array
    {
        return [
            'counters' => [
                'success' => $this->counters['success'],
                'failed' => $this->counters['failed'],
                'missed' => $this->counters['missed'],
            ],
            'groups' => array_map(
                static fn (JobGroupData $group): array => $group->toArray(),
                $this->groups,
            ),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
