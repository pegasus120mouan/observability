<?php

namespace App\Http\Requests;

use App\Enums\ApplicationType;
use App\Enums\HostEnvironment;
use App\Models\Agent;
use App\Support\ApmCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAgentApmRequest extends FormRequest
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
        /** @var Agent|null $agent */
        $agent = $this->attributes->get('agent');
        $organizationId = $agent?->organization_id;

        return [
            'timestamp' => ['nullable', 'date'],
            'applications' => ['required', 'array', 'min:1', 'max:'.ApmCatalog::maxApplicationsPerRequest()],
            'applications.*.name' => ['required', 'string', 'max:255'],
            'applications.*.type' => ['required', Rule::enum(ApplicationType::class)],
            'applications.*.environment' => ['nullable', Rule::enum(HostEnvironment::class)],
            'applications.*.version' => ['nullable', 'string', 'max:64'],
            'applications.*.endpoint' => ['nullable', 'url', 'max:2048'],
            'applications.*.host_id' => [
                'nullable',
                'integer',
                Rule::exists('hosts', 'id')->where(fn ($query) => $organizationId ? $query->where('organization_id', $organizationId) : $query),
            ],
            'applications.*.metrics' => ['required', 'array'],
            'applications.*.metrics.request_count' => ['required', 'integer', 'min:0', 'max:100000000'],
            'applications.*.metrics.error_count' => ['required', 'integer', 'min:0', 'max:100000000'],
            'applications.*.metrics.response_time_avg' => ['required', 'integer', 'min:0', 'max:600000'],
            'applications.*.metrics.response_time_p95' => ['required', 'integer', 'min:0', 'max:600000'],
            'applications.*.metrics.status_codes' => ['nullable', 'array', 'max:20'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->input('applications', []) as $index => $application) {
                    $metrics = is_array($application['metrics'] ?? null) ? $application['metrics'] : [];
                    $requests = (int) ($metrics['request_count'] ?? 0);
                    $errors = (int) ($metrics['error_count'] ?? 0);

                    if ($errors > $requests) {
                        $validator->errors()->add(
                            "applications.{$index}.metrics.error_count",
                            'Error count cannot exceed request count.',
                        );
                    }

                    foreach ($metrics['status_codes'] ?? [] as $code => $count) {
                        if (! ApmCatalog::isAllowedStatusCode((string) $code) || ! is_numeric($count) || (int) $count < 0) {
                            $validator->errors()->add(
                                "applications.{$index}.metrics.status_codes",
                                'Status codes must use HTTP status keys and non-negative counts.',
                            );
                            break;
                        }
                    }
                }
            },
        ];
    }
}
