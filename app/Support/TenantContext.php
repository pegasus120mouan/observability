<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Facades\Context;

class TenantContext
{
    public function set(?Organization $organization): void
    {
        if ($organization === null) {
            Context::forget('organization_id');
            Context::forgetHidden('organization');

            return;
        }

        Context::add('organization_id', $organization->id);
        Context::addHidden('organization', $organization);
    }

    public function id(): ?int
    {
        $organizationId = Context::get('organization_id');

        return $organizationId !== null ? (int) $organizationId : null;
    }

    public function organization(): ?Organization
    {
        $organization = Context::getHidden('organization');

        return $organization instanceof Organization ? $organization : null;
    }

    public function hasOrganization(): bool
    {
        return $this->id() !== null;
    }
}
