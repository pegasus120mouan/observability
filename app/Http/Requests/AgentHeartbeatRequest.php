<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentHeartbeatRequest extends FormRequest
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
            'agent_id' => ['nullable', 'string', 'max:64'],
            'timestamp' => ['nullable', 'date'],
            'version' => ['nullable', 'string', 'max:50'],
            'ip_address' => ['nullable', 'ip'],
        ];
    }
}
