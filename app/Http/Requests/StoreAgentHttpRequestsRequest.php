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
            'requests' => ['required', 'array', 'min:1', 'max:'.ApmCatalog::maxHttpRequestsPerRequest()],
            'requests.*.occurred_at' => ['required', 'date'],
            'requests.*.method' => ['nullable', 'string', 'max:16'],
            'requests.*.resource' => ['required', 'string', 'max:512'],
            'requests.*.status_code' => ['required', 'integer'],
            'requests.*.duration_us' => ['nullable', 'integer', 'min:0', 'max:600000000'],
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

                foreach ($this->input('requests', []) as $index => $request) {
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
