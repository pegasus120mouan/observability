<?php

namespace App\Http\Requests;

use App\Enums\AlertSeverity;
use App\Enums\IncidentPriority;
use App\Models\Incident;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Incident::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->id();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', Rule::enum(AlertSeverity::class)],
            'priority' => ['nullable', Rule::enum(IncidentPriority::class)],
            'host_id' => [
                'nullable',
                'integer',
                Rule::exists('hosts', 'id')->where(fn ($query) => $organizationId ? $query->where('organization_id', $organizationId) : $query),
            ],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where(fn ($query) => $organizationId ? $query->where('organization_id', $organizationId) : $query),
            ],
        ];
    }
}
