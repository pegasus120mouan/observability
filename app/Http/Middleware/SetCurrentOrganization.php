<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $user->loadMissing(['organizations', 'memberships.role']);

        $organization = $this->resolveOrganization($request, $user);

        if ($organization !== null && ! $user->isSuperAdmin() && ! $user->belongsToOrganization($organization)) {
            abort(404);
        }

        if ($organization !== null && $organization->isSuspended() && ! $user->isSuperAdmin()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This organization is suspended.',
                    'errors' => (object) [],
                ], 403);
            }

            abort(403, 'This organization is suspended.');
        }

        if ($organization === null && ! $user->isSuperAdmin()) {
            abort(403, 'No organization is available for this account.');
        }

        $this->tenantContext->set($organization);

        if ($organization !== null) {
            if ($request->hasSession()) {
                $request->session()->put('current_organization_id', $organization->id);
            }

            if ($user->current_organization_id !== $organization->id) {
                $user->forceFill(['current_organization_id' => $organization->id])->save();
            }
        }

        view()->share([
            'currentOrganization' => $organization,
            'availableOrganizations' => $user->isSuperAdmin()
                ? Organization::query()->orderBy('name')->orderBy('id')->get()
                : $user->organizations->sortBy('name')->values(),
        ]);

        return $next($request);
    }

    private function resolveOrganization(Request $request, mixed $user): ?Organization
    {
        $requestedId = $request->header('X-Organization-Id')
            ?? ($request->hasSession() ? $request->session()->get('current_organization_id') : null)
            ?? $user->current_organization_id;

        if ($requestedId) {
            $organization = Organization::query()->find($requestedId);

            if ($organization !== null) {
                return $organization;
            }
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        return $user->organizations->first();
    }
}
