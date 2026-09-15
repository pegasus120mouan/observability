<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\IngestMetricSamplesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentMetricsRequest;
use App\Models\Agent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class MetricsController extends Controller
{
    public function __invoke(StoreAgentMetricsRequest $request, IngestMetricSamplesAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $collectedAt = $request->date('timestamp') instanceof Carbon
            ? $request->date('timestamp')
            : now();

        $result = $action->handle(
            $agent,
            $request->validated('metrics'),
            $collectedAt,
        );

        return ApiResponse::success([
            'inserted' => $result['inserted'],
            'host_id' => $result['host']->id,
            'host_status' => $result['host']->status->value,
        ], 'Metrics accepted.', status: 202);
    }
}
