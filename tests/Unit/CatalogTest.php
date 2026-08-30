<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Yaaqen\JobHealth\Support\Catalog;

it('preserves catalog order and puts configured groups first', function (): void {
    $catalog = new Catalog(new Repository([
        'job-health' => [
            'groups' => [
                'backup' => 'Backups',
                'seo' => 'SEO',
            ],
            'jobs' => [
                'sitemap' => [
                    'name' => 'Sitemap',
                    'group' => 'seo',
                    'cron' => '0 0 * * *',
                ],
                'dump' => [
                    'name' => 'Dump',
                    'group' => 'backup',
                    'cron' => '0 3 * * *',
                ],
                'otp' => [
                    'name' => 'OTP',
                    'group' => 'ops',
                    'cron' => '0 2 * * *',
                ],
            ],
        ],
    ]));

    $grouped = $catalog->grouped();

    expect(array_column($grouped, 'key'))->toBe(['backup', 'seo', 'ops'])
        ->and($grouped[0]['label'])->toBe('Backups')
        ->and(array_map(fn ($job) => $job->key, $grouped[0]['jobs']))->toBe(['dump'])
        ->and($catalog->has('sitemap'))->toBeTrue()
        ->and($catalog->has('unknown'))->toBeFalse();
});
