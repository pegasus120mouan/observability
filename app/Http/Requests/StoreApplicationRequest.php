<?php

namespace App\Http\Requests;

use App\Enums\ApplicationType;
use App\Enums\HostEnvironment;
use App\Models\Application;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Application::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ApplicationType::class)],
            'environment' => ['required', Rule::enum(HostEnvironment::class)],
            'version' => ['nullable', 'string', 'max:64'],
            'endpoint' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'],
            'host_id' => [
                'nullable',
                'integer',
                Rule::exists('hosts', 'id')->where(fn ($query) => $organizationId ? $query->where('organization_id', $organizationId) : $query),
            ],
        ];
    }
}
