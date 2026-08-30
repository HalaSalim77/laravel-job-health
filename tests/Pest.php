<?php

declare(strict_types=1);

use Carbon\Carbon;
use Yaaqen\JobHealth\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

afterEach(function (): void {
    Carbon::setTestNow();
});
