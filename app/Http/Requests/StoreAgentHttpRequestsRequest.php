<?php

namespace App\Http\Requests;

use App\Enums\ApplicationType;
use App\Support\ApmCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAgentHttpRequestsRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ApplicationType::class)],
            'requests' => ['nullable', 'array', 'max:'.ApmCatalog::maxHttpRequestsPerRequest()],
            'requests.*.occurred_at' => ['required', 'date'],
            'requests.*.method' => ['nullable', 'string', 'max:16'],
            'requests.*.resource' => ['required', 'string', 'max:512'],
            'requests.*.status_code' => ['required', 'integer'],
            'requests.*.duration_us' => ['nullable', 'integer', 'min:0', 'max:600000000'],
            'requests.*.client_ip' => ['nullable', 'ip'],
            'sample' => ['nullable', 'array'],
            'sample.request_count' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'sample.error_count' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'sample.response_time_avg' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'sample.response_time_p95' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'sample.req_per_sec' => ['nullable', 'numeric', 'min:0'],
            'sample.busy_workers' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sample.idle_workers' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sample.bytes_per_sec' => ['nullable', 'numeric', 'min:0'],
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

                $requests = $this->input('requests', []);
                $sample = $this->input('sample');
                $hasRequests = is_array($requests) && $requests !== [];
                $hasSample = is_array($sample) && $sample !== [];

                if (! $hasRequests && ! $hasSample) {
                    $validator->errors()->add('requests', 'Provide HTTP request samples or a live Apache status sample.');

                    return;
                }

                foreach ($hasRequests ? $requests : [] as $index => $request) {
                    $code = (string) ($request['status_code'] ?? '');

                    if (! ApmCatalog::isAllowedStatusCode($code)) {
                        $validator->errors()->add(
                            "requests.{$index}.status_code",
                            'Status codes must be valid HTTP statuses.',
                        );
                    }
                }
            },
        ];
    }
}
