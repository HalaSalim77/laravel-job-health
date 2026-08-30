<?php

declare(strict_types=1);

namespace Yaaqen\JobHealth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Yaaqen\JobHealth\Services\JobHealthService;

final class DashboardController
{
    public function index(JobHealthService $service): View
    {
        return view('job-health::dashboard', [
            'dashboard' => $service->dashboard(),
        ]);
    }

    public function api(JobHealthService $service): JsonResponse
    {
        return response()->json($service->dashboard()->toArray());
    }
}
