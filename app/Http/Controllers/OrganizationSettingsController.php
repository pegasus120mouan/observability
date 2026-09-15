<?php

namespace App\Http\Controllers;

use App\Actions\UpdateOrganizationAction;
use App\Enums\OrganizationStatus;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    public function edit(TenantContext $tenantContext): View
    {
        $organization = $tenantContext->organization();

        abort_if($organization === null, 404);

        $this->authorize('update', $organization);

        return view('settings.organization', [
            'organization' => $organization,
            'statuses' => OrganizationStatus::cases(),
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        TenantContext $tenantContext,
        UpdateOrganizationAction $action,
    ): RedirectResponse {
        $organization = $tenantContext->organization();

        abort_if($organization === null, 404);

        $action->handle($organization, $request->validated(), $request->user());

        return back()->with('status', 'Organization settings saved.');
    }
}
