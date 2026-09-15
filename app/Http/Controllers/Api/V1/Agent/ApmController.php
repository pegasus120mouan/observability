<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\IngestApplicationMetricsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentApmRequest;
use App\Models\Agent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ApmController extends Controller
{
    public function __invoke(StoreAgentApmRequest $request, IngestApplicationMetricsAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $collectedAt = $request->date('timestamp') instanceof Carbon
            ? $request->date('timestamp')
            : now();

        $result = $action->handle(
            $agent,
            $request->validated('applications'),
            $collectedAt,
        );

        return ApiResponse::success($result, 'Application metrics accepted.', status: 202);
    }
}
