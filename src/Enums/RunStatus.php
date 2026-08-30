<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Enums;

enum RunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Failed = 'failed';
}
