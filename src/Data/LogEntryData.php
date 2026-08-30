<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Data;

use JsonSerializable;

final readonly class LogEntryData implements JsonSerializable
{
    public function __construct(
        public string $time,
        public string $source,
        public string $actor,
        public string $duration,
        public string $status,
        public ?string $extra = null,
        public ?string $error = null,
    ) {}

    /**
     * @return array{time: string, source: string, actor: string, duration: string, status: string, extra?: string, error?: string}
     */
    public function toArray(): array
    {
        $data = [
            'time' => $this->time,
            'source' => $this->source,
            'actor' => $this->actor,
            'duration' => $this->duration,
            'status' => $this->status,
        ];

        if ($this->extra !== null && $this->extra !== '') {
            $data['extra'] = $this->extra;
        }

        if ($this->error !== null && $this->error !== '') {
            $data['error'] = $this->error;
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
