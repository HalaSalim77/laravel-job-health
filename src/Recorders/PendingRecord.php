<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Recorders;

use Yaaqen\JobHealth\Enums\RunSource;
use Yaaqen\JobHealth\Models\JobRun;

final class PendingRecord
{
    private string $source = RunSource::Custom->value;

    private ?int $userId = null;

    private ?string $userName = null;

    /** @var array<string, mixed>|null */
    private ?array $meta = null;

    private ?JobRun $run = null;

    public function __construct(
        private readonly JobRunRecorder $recorder,
        private readonly string $key,
    ) {}

    public function fromSchedule(): self
    {
        $this->source = RunSource::Schedule->value;
        $this->userId = null;
        $this->userName = null;

        return $this;
    }

    public function manual(?int $userId = null, ?string $name = null): self
    {
        $this->source = RunSource::Manual->value;
        $this->userId = $userId;
        $this->userName = $name;

        return $this;
    }

    public function source(string $source, ?int $userId = null, ?string $name = null): self
    {
        $this->source = $source;
        $this->userId = $userId;
        $this->userName = $name;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        $this->meta = $meta;

        return $this;
    }

    public function start(): ?JobRun
    {
        if (! $this->recorder->isKnown($this->key)) {
            return null;
        }

        $this->run = $this->recorder->start(
            $this->key,
            $this->source,
            $this->userId,
            $this->userName,
            $this->meta,
        );

        return $this->run;
    }

    public function succeed(?int $ok = null, ?int $fail = null): ?JobRun
    {
        $run = $this->ensureStarted();

        if ($run === null) {
            return null;
        }

        return $this->recorder->succeed($run, $ok, $fail);
    }

    public function fail(string $message, ?int $ok = null, ?int $fail = null): ?JobRun
    {
        $run = $this->ensureStarted();

        if ($run === null) {
            return null;
        }

        return $this->recorder->fail($run, $message, $ok, $fail);
    }

    private function ensureStarted(): ?JobRun
    {
        return $this->run ?? $this->start();
    }
}
