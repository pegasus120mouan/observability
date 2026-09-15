<?php

namespace App\Http\Requests;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null && ($this->user()?->can('update', $organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organization = $this->organization();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('organizations', 'slug')->ignore($organization),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(OrganizationStatus::class)],
            'timezone' => ['required', 'timezone'],
            'metric_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'log_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'audit_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function organization(): ?Organization
    {
        $organization = $this->route('organization');

        if ($organization instanceof Organization) {
            return $organization;
        }

        return app(TenantContext::class)->organization();
    }
}
