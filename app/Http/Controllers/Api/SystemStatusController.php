<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemRequirementService;
use Illuminate\Http\JsonResponse;

class SystemStatusController extends Controller
{
    public function __construct(
        protected SystemRequirementService $systemRequirementService
    ) {}

    /**
     * Get system environment, drivers, and CLI dump tool requirement status.
     */
    public function status(): JsonResponse
    {
        $status = $this->systemRequirementService->check();

        return response()->json($status);
    }
}
