<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Health\HealthReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly HealthReport $healthReport,
    ) {}

    public function page(): InertiaResponse
    {
        return Inertia::render('AdminHealthPage', [
            'health' => $this->healthReport->snapshot(),
        ]);
    }

    public function queue(): JsonResponse
    {
        return response()->json($this->healthReport->queue());
    }
}
