<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\SwitchOrganizationRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

class CurrentOrganizationController extends Controller
{
    public function update(SwitchOrganizationRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $organization = $request->organization();
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->belongsToOrganization($organization)) {
            abort(404);
        }

        $request->session()->put('current_organization_id', $organization->id);
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        $auditLogger->log(
            AuditAction::OrganizationSwitched,
            $organization,
            newValues: ['organization' => $organization->name],
            organization: $organization,
            actor: $user,
        );

        return redirect()
            ->route('overview')
            ->with('status', 'Switched to '.$organization->name.'.');
    }
}
