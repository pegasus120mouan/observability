<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrganizationAction;
use App\Actions\UpdateOrganizationAction;
use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount('users')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('organizations.index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Organization::class);

        return view('organizations.create', [
            'statuses' => OrganizationStatus::cases(),
        ]);
    }

    public function store(StoreOrganizationRequest $request, CreateOrganizationAction $action): RedirectResponse
    {
        $organization = $action->handle($request->validated(), $request->user());

        return redirect()
            ->route('organizations.edit', $organization)
            ->with('status', 'Organization created.');
    }

    public function edit(Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('organizations.edit', [
            'organization' => $organization,
            'statuses' => OrganizationStatus::cases(),
        ]);
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        UpdateOrganizationAction $action,
    ): RedirectResponse {
        $action->handle($organization, $request->validated(), $request->user());

        return back()->with('status', 'Organization updated.');
    }

    public function destroy(Organization $organization, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $auditLogger->log(
            AuditAction::OrganizationDeleted,
            $organization,
            oldValues: ['name' => $organization->name, 'slug' => $organization->slug],
            organization: $organization,
        );

        $organization->delete();

        return redirect()
            ->route('organizations.index')
            ->with('status', 'Organization deleted.');
    }
}
