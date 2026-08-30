<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Enums;

enum HealthStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Missed = 'missed';
    case Running = 'running';
    case Never = 'never';
}
