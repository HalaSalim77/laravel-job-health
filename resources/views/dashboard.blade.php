<?php

declare(strict_types=1);

use Yaaqen\JobHealth\Data\DashboardData;
use Yaaqen\JobHealth\Data\HistoryDayData;
use Yaaqen\JobHealth\Data\JobCardData;
use Yaaqen\JobHealth\Data\LogEntryData;
use Yaaqen\JobHealth\Enums\HealthStatus;

/** @var DashboardData $dashboard */
?>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('job-health::job-health.title') }}</title>
    <style>
        :root {
            --jh-bg: #0f1419;
            --jh-surface: #1a1f27;
            --jh-surface-2: #232a34;
            --jh-border: #2e3a48;
            --jh-text: #e7ecf1;
            --jh-muted: #8b9aab;
            --jh-ok: #3dba7a;
            --jh-fail: #e05656;
            --jh-miss: #e0a84a;
            --jh-run: #5b9fd6;
            --jh-never: #6b7785;
            --jh-none: #3a4654;
        }
        * { box-sizing: border-box; }
        body.job-health-page {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            background: var(--jh-bg);
            color: var(--jh-text);
            line-height: 1.45;
        }
        .job-health {
            direction: ltr;
            max-width: 1120px;
            margin: 0 auto;
            padding: 32px 20px 64px;
        }
        .job-health[dir="rtl"] { direction: rtl; }
        .jh-header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 28px;
        }
        .jh-header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 650;
            letter-spacing: -0.02em;
        }
        .jh-counters {
            display: flex;
            gap: 10px;
        }
        .jh-counter {
            min-width: 88px;
            padding: 10px 14px;
            background: var(--jh-surface);
            border: 1px solid var(--jh-border);
            border-radius: 10px;
            text-align: center;
        }
        .jh-counter__value {
            font-size: 1.35rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        .jh-counter__label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--jh-muted);
        }
        .jh-counter--success .jh-counter__value { color: var(--jh-ok); }
        .jh-counter--failed .jh-counter__value { color: var(--jh-fail); }
        .jh-counter--missed .jh-counter__value { color: var(--jh-miss); }
        .jh-group {
            background: var(--jh-surface);
            border: 1px solid var(--jh-border);
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .jh-group > summary {
            cursor: pointer;
            list-style: none;
            padding: 14px 18px;
            font-weight: 600;
            background: var(--jh-surface-2);
        }
        .jh-group > summary::-webkit-details-marker { display: none; }
        .jh-job {
            border-top: 1px solid var(--jh-border);
        }
        .jh-job > summary {
            cursor: pointer;
            list-style: none;
            display: grid;
            grid-template-columns: minmax(140px, 1.4fr) minmax(90px, 0.7fr) minmax(90px, 0.6fr) minmax(180px, 1.6fr) auto;
            gap: 12px;
            align-items: center;
            padding: 12px 18px;
        }
        .jh-job > summary::-webkit-details-marker { display: none; }
        .jh-job__name { font-weight: 600; }
        .jh-job__meta { color: var(--jh-muted); font-size: 0.85rem; }
        .jh-job__counts { display: block; font-size: 0.75rem; color: var(--jh-muted); font-weight: 400; }
        .jh-bar {
            display: flex;
            gap: 2px;
            height: 18px;
            align-items: center;
        }
        .jh-bar__cell {
            flex: 1;
            height: 10px;
            border-radius: 2px;
            position: relative;
        }
        .jh-bar__cell--none { background: var(--jh-none); }
        .jh-bar__cell--ok { background: var(--jh-ok); }
        .jh-bar__cell--fail { background: var(--jh-fail); }
        .jh-bar__cell--miss { background: var(--jh-miss); }
        .jh-bar__cell:hover::after {
            content: attr(data-tip);
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #0b1014;
            color: var(--jh-text);
            border: 1px solid var(--jh-border);
            font-size: 0.72rem;
            padding: 4px 8px;
            border-radius: 6px;
            white-space: nowrap;
            z-index: 5;
            pointer-events: none;
        }
        .jh-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 650;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .jh-badge--success { background: rgba(61, 186, 122, 0.18); color: var(--jh-ok); }
        .jh-badge--failed { background: rgba(224, 86, 86, 0.18); color: var(--jh-fail); }
        .jh-badge--missed { background: rgba(224, 168, 74, 0.18); color: var(--jh-miss); }
        .jh-badge--running { background: rgba(91, 159, 214, 0.18); color: var(--jh-run); }
        .jh-badge--never { background: rgba(107, 119, 133, 0.22); color: var(--jh-never); }
        .jh-panel { padding: 0 18px 16px; }
        .jh-empty {
            padding: 12px 14px;
            background: rgba(224, 168, 74, 0.1);
            border: 1px solid rgba(224, 168, 74, 0.28);
            border-radius: 8px;
            color: var(--jh-miss);
            font-size: 0.9rem;
        }
        .jh-table-wrap { overflow-x: auto; }
        table.jh-log {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        table.jh-log th,
        table.jh-log td {
            text-align: start;
            padding: 8px 10px;
            border-bottom: 1px solid var(--jh-border);
        }
        table.jh-log th {
            color: var(--jh-muted);
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .jh-log__error td {
            background: rgba(224, 86, 86, 0.12);
            color: var(--jh-fail);
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 0.8rem;
        }
        .jh-log__extra { color: var(--jh-muted); font-size: 0.78rem; }
        @media (max-width: 800px) {
            .jh-job > summary {
                grid-template-columns: 1fr auto;
            }
            .jh-job__schedule, .jh-job__last { display: none; }
        }
    </style>
</head>
<body class="job-health-page">
<div class="job-health">
    <header class="jh-header">
        <h1>{{ __('job-health::job-health.title') }}</h1>
        <div class="jh-counters">
            <div class="jh-counter jh-counter--success">
                <div class="jh-counter__value">{{ $dashboard->counters['success'] }}</div>
                <div class="jh-counter__label">{{ __('job-health::job-health.success') }}</div>
            </div>
            <div class="jh-counter jh-counter--failed">
                <div class="jh-counter__value">{{ $dashboard->counters['failed'] }}</div>
                <div class="jh-counter__label">{{ __('job-health::job-health.failed') }}</div>
            </div>
            <div class="jh-counter jh-counter--missed">
                <div class="jh-counter__value">{{ $dashboard->counters['missed'] }}</div>
                <div class="jh-counter__label">{{ __('job-health::job-health.missed') }}</div>
            </div>
        </div>
    </header>

    @forelse ($dashboard->groups as $group)
        <details class="jh-group" open>
            <summary>{{ $group->label }}</summary>
            @foreach ($group->jobs as $job)
                @php
                    /** @var JobCardData $job */
                    $statusKey = $job->status->value;
                @endphp
                <details class="jh-job">
                    <summary>
                        <div class="jh-job__name">
                            {{ $job->name }}
                            @if ($job->countsLabel)
                                <span class="jh-job__counts">{{ $job->countsLabel }}</span>
                            @endif
                        </div>
                        <div class="jh-job__meta jh-job__schedule">{{ $job->scheduleLabel }}</div>
                        <div class="jh-job__meta jh-job__last">{{ $job->lastRunHuman }}</div>
                        <div class="jh-bar" aria-hidden="true">
                            @foreach ($job->history as $day)
                                @php
                                    /** @var HistoryDayData $day */
                                    $tip = $day->date.' · '.$day->status;
                                    if ($day->error) {
                                        $tip .= ' · '.$day->error;
                                    }
                                @endphp
                                <span class="jh-bar__cell jh-bar__cell--{{ $day->status }}" data-tip="{{ $tip }}"></span>
                            @endforeach
                        </div>
                        <span class="jh-badge jh-badge--{{ $statusKey }}">
                            {{ __('job-health::job-health.'.$statusKey) }}
                        </span>
                    </summary>
                    <div class="jh-panel">
                        @if ($job->status === HealthStatus::Missed && $job->logs === [] && $job->missedExpectedAt)
                            <p class="jh-empty">{{ __('job-health::job-health.never_ran', ['time' => $job->missedExpectedAt]) }}</p>
                        @else
                            <div class="jh-table-wrap">
                                <table class="jh-log">
                                    <thead>
                                    <tr>
                                        <th>{{ __('job-health::job-health.columns.time') }}</th>
                                        <th>{{ __('job-health::job-health.columns.source') }}</th>
                                        <th>{{ __('job-health::job-health.columns.by') }}</th>
                                        <th>{{ __('job-health::job-health.columns.duration') }}</th>
                                        <th>{{ __('job-health::job-health.columns.status') }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse ($job->logs as $log)
                                        @php /** @var LogEntryData $log */ @endphp
                                        <tr>
                                            <td>{{ $log->time }}</td>
                                            <td>{{ $log->source }}</td>
                                            <td>{{ $log->actor }}</td>
                                            <td>{{ $log->duration }}</td>
                                            <td>
                                                <span class="jh-badge jh-badge--{{ $log->status }}">{{ __('job-health::job-health.'.$log->status) }}</span>
                                                @if ($log->extra)
                                                    <div class="jh-log__extra">{{ $log->extra }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                        @if ($log->error)
                                            <tr class="jh-log__error">
                                                <td colspan="5">{{ $log->error }}</td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="5">{{ __('job-health::job-health.last_run_never') }}</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </details>
            @endforeach
        </details>
    @empty
        <p class="jh-empty">{{ __('job-health::job-health.not_scheduled') }}</p>
    @endforelse
</div>
</body>
</html>
