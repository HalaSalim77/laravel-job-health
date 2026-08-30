<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth;

use Yaaqen\JobHealth\Recorders\JobRunRecorder;
use Yaaqen\JobHealth\Recorders\PendingRecord;

final class JobHealth
{
    public function __construct(
        private readonly JobRunRecorder $recorder,
    ) {}

    public function record(string $key): PendingRecord
    {
        return new PendingRecord($this->recorder, $key);
    }
}
