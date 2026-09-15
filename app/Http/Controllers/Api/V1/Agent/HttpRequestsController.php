<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\IngestHttpRequestsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentHttpRequestsRequest;
use App\Models\Agent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class HttpRequestsController extends Controller
{
    public function __invoke(StoreAgentHttpRequestsRequest $request, IngestHttpRequestsAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $collectedAt = $request->date('timestamp') instanceof Carbon
            ? $request->date('timestamp')
            : now();

        $result = $action->handle(
            $agent,
            $request->safe()->only(['name', 'type']),
            $request->validated('requests') ?? [],
            $collectedAt,
            $request->validated('sample') ?? [],
        );

        return ApiResponse::success($result, 'HTTP requests accepted.', status: 202);
    }
}
