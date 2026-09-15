<?php

namespace App\Http\Requests;

use App\Enums\AgentPlatform;
use App\Enums\HostEnvironment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enrollment_token' => ['required', 'string'],
            'hostname' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'operating_system' => ['nullable', 'string', 'max:100'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'architecture' => ['nullable', 'string', 'max:50'],
            'environment' => ['nullable', Rule::enum(HostEnvironment::class)],
            'platform' => ['nullable', Rule::enum(AgentPlatform::class)],
            'version' => ['nullable', 'string', 'max:50'],
        ];
    }
}
