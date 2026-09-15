<?php

namespace App\Http\Requests;

use App\Enums\ApplicationType;
use App\Support\ApmCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('agent') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timestamp' => ['nullable', 'date'],
            'services' => ['present', 'array', 'max:'.ApmCatalog::maxApplicationsPerRequest()],
            'services.*.name' => ['required', 'string', 'max:255'],
            'services.*.type' => ['required', Rule::enum(ApplicationType::class)],
            'services.*.version' => ['nullable', 'string', 'max:64'],
        ];
    }
}
