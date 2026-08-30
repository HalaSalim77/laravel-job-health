<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Facades;

use Illuminate\Support\Facades\Facade;
use Yaaqen\JobHealth\Recorders\PendingRecord;

/**
 * @method static PendingRecord record(string $key)
 *
 * @see \Yaaqen\JobHealth\JobHealth
 */
final class JobHealth extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Yaaqen\JobHealth\JobHealth::class;
    }
}
