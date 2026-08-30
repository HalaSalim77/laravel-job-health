<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Yaaqen\JobHealth\Enums\RunStatus;

final class JobRun extends Model
{
    use Prunable;

    protected $table = 'job_runs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_key',
        'source',
        'triggered_by',
        'triggered_by_name',
        'status',
        'success_count',
        'failed_count',
        'duration_ms',
        'error_message',
        'meta',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RunStatus::class,
            'meta' => 'array',
            'triggered_by' => 'integer',
            'success_count' => 'integer',
            'failed_count' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        $days = (int) config('job-health.prune_days', 30);

        return static::query()
            ->where('started_at', '<', now()->subDays($days));
    }

    public function hasCounts(): bool
    {
        return $this->success_count !== null || $this->failed_count !== null;
    }
}
