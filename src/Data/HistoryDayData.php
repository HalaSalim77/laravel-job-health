<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Data;

use JsonSerializable;

final readonly class HistoryDayData implements JsonSerializable
{
    public function __construct(
        public string $date,
        public string $status,
        public ?string $error = null,
    ) {}

    /**
     * @return array{date: string, status: string, error?: string}
     */
    public function toArray(): array
    {
        $data = [
            'date' => $this->date,
            'status' => $this->status,
        ];

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
