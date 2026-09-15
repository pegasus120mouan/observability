<?php

namespace App\Http\Requests;

use App\Enums\AlertSeverity;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->id();

        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['sometimes', Rule::enum(AlertSeverity::class)],
            'priority' => ['sometimes', Rule::enum(IncidentPriority::class)],
            'status' => ['sometimes', Rule::enum(IncidentStatus::class)],
            'root_cause' => ['nullable', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where(fn ($query) => $organizationId ? $query->where('organization_id', $organizationId) : $query),
            ],
        ];
    }
}
