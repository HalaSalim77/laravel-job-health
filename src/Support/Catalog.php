<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;
use Yaaqen\JobHealth\Data\JobDefinition;

final class Catalog
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @return array<string, JobDefinition>
     */
    public function jobs(): array
    {
        $jobs = [];

        foreach ($this->config->get('job-health.jobs', []) as $key => $config) {
            if (! is_string($key) || ! is_array($config)) {
                continue;
            }

            $jobs[$key] = JobDefinition::fromConfig($key, $config);
        }

        return $jobs;
    }

    public function get(string $key): ?JobDefinition
    {
        return $this->jobs()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config->get('job-health.jobs', []));
    }

    /**
     * @return list<array{key: string, label: string, jobs: list<JobDefinition>}>
     */
    public function grouped(): array
    {
        $jobsByGroup = [];

        foreach ($this->jobs() as $job) {
            $jobsByGroup[$job->group][] = $job;
        }

        if ($jobsByGroup === []) {
            return [];
        }

        $configured = $this->config->get('job-health.groups', []);
        $orderedKeys = [];

        foreach (array_keys($configured) as $groupKey) {
            if (isset($jobsByGroup[$groupKey])) {
                $orderedKeys[] = (string) $groupKey;
            }
        }

        foreach (array_keys($jobsByGroup) as $groupKey) {
            if (! in_array($groupKey, $orderedKeys, true)) {
                $orderedKeys[] = $groupKey;
            }
        }

        $groups = [];

        foreach ($orderedKeys as $groupKey) {
            $label = $configured[$groupKey] ?? Str::headline(str_replace(['-', '_'], ' ', $groupKey));

            $groups[] = [
                'key' => $groupKey,
                'label' => (string) $label,
                'jobs' => $jobsByGroup[$groupKey],
            ];
        }

        return $groups;
    }
}
