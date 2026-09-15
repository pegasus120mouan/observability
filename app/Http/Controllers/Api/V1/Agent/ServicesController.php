<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\DiscoverHostServicesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAgentServicesRequest;
use App\Models\Agent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServicesController extends Controller
{
    public function __invoke(StoreAgentServicesRequest $request, DiscoverHostServicesAction $action): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $result = $action->handle(
            $agent,
            $request->validated('services'),
        );

        return ApiResponse::success($result, 'Host services accepted.', status: 202);
    }
}
