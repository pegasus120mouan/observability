<?php

namespace App\Http\Requests;

use App\Enums\LogLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('agent') !== null;
    }

    protected function prepareForValidation(): void
    {
        $logs = $this->input('logs');

        if (! is_array($logs) && $this->filled('message')) {
            $logs = [$this->only([
                'timestamp',
                'level',
                'source',
                'message',
                'facility',
                'event_id',
                'ip_address',
                'user',
                'username',
                'process',
                'metadata',
            ])];
        }

        if (! is_array($logs)) {
            return;
        }

        $normalized = [];

        foreach ($logs as $log) {
            if (! is_array($log)) {
                continue;
            }

            if (isset($log['level'])) {
                $log['level'] = strtolower((string) $log['level']);
            }

            $normalized[] = $log;
        }

        $this->merge(['logs' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'timestamp' => ['nullable', 'date'],
            'logs' => ['required', 'array', 'min:1', 'max:'.(int) config('platform.logs.max_entries_per_request')],
            'logs.*.timestamp' => ['nullable', 'date'],
            'logs.*.level' => ['required', Rule::enum(LogLevel::class)],
            'logs.*.message' => ['required', 'string', 'max:8000'],
            'logs.*.source' => ['nullable', 'string', 'max:100'],
            'logs.*.facility' => ['nullable', 'string', 'max:100'],
            'logs.*.event_id' => ['nullable', 'string', 'max:64'],
            'logs.*.ip_address' => ['nullable', 'ip'],
            'logs.*.user' => ['nullable', 'string', 'max:100'],
            'logs.*.username' => ['nullable', 'string', 'max:100'],
            'logs.*.process' => ['nullable', 'string', 'max:100'],
            'logs.*.metadata' => ['nullable', 'array'],
        ];
    }
}
