<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\IngestLogEntriesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentLogsRequest;
use App\Models\Agent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class LogsController extends Controller
{
    public function __invoke(StoreAgentLogsRequest $request, IngestLogEntriesAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $fallback = $request->date('timestamp') instanceof Carbon
            ? $request->date('timestamp')
            : now();

        $result = $action->handle(
            $agent,
            $request->validated('logs'),
            $fallback,
        );

        return ApiResponse::success([
            'inserted' => $result['inserted'],
            'skipped' => $result['skipped'],
            'host_id' => $result['host']->id,
        ], 'Logs accepted.', status: 202);
    }
}
