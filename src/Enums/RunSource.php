<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Enums;

enum RunSource: string
{
    case Schedule = 'schedule';
    case Manual = 'manual';
    case Custom = 'custom';
}
