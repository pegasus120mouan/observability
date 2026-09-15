<?php

namespace App\Http\Controllers;

use App\Actions\CreateEnrollmentTokenAction;
use App\Actions\RotateAgentKeyAction;
use App\Enums\AgentStatus;
use App\Enums\AuditAction;
use App\Http\Requests\StoreEnrollmentTokenRequest;
use App\Models\Agent;
use App\Models\EnrollmentToken;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $this->authorize('viewAny', Agent::class);

        $organization = $tenantContext->organization();
        abort_if($organization === null && ! request()->user()?->isSuperAdmin(), 404);

        $agents = Agent::query()
            ->with('host')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);

        $tokens = EnrollmentToken::query()
            ->with('creator')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('agents.index', [
            'agents' => $agents,
            'tokens' => $tokens,
            'organization' => $organization,
        ]);
    }

    public function storeToken(StoreEnrollmentTokenRequest $request, CreateEnrollmentTokenAction $action, TenantContext $tenantContext): RedirectResponse
    {
        $organization = $tenantContext->organization();
        abort_if($organization === null, 404);

        $result = $action->handle(
            $organization,
            $request->validated('name'),
            $request->user(),
            $request->integer('expires_in_days') ?: 30,
        );

        return back()
            ->with('status', 'Enrollment token created. Copy it now; it will not be shown again.')
            ->with('enrollment_token_plain', $result['plain_text']);
    }

    public function revokeToken(EnrollmentToken $enrollmentToken, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('create', Agent::class);

        $enrollmentToken->forceFill(['revoked_at' => now()])->save();

        $auditLogger->log(
            AuditAction::EnrollmentTokenRevoked,
            $enrollmentToken,
            oldValues: ['name' => $enrollmentToken->name],
        );

        return back()->with('status', 'Enrollment token revoked.');
    }

    public function rotate(Agent $agent, RotateAgentKeyAction $action): RedirectResponse
    {
        $this->authorize('update', $agent);

        $result = $action->handle($agent, request()->user());

        return back()
            ->with('status', 'API key rotated. Copy the new key now; it will not be shown again.')
            ->with('rotated_api_key', $result['api_key'])
            ->with('rotated_agent_uid', $agent->agent_uid);
    }

    public function revoke(Agent $agent, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $agent);

        $agent->forceFill([
            'status' => AgentStatus::Revoked,
            'revoked_at' => now(),
        ])->save();

        $auditLogger->log(
            AuditAction::AgentRevoked,
            $agent,
            newValues: ['agent_uid' => $agent->agent_uid],
        );

        return back()->with('status', 'Agent revoked.');
    }
}
