<?php

namespace App\Http\Requests;

use App\Enums\MetricType;
use App\Support\MetricCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAgentMetricsRequest extends FormRequest
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
            'metrics' => ['required', 'array', 'min:1', 'max:'.(int) config('platform.metrics.max_points_per_request')],
            'metrics.*.type' => ['required', Rule::enum(MetricType::class)],
            'metrics.*.name' => ['required', 'string', 'max:64'],
            'metrics.*.value' => ['required', 'numeric'],
            'metrics.*.unit' => ['nullable', 'string', 'max:32'],
            'metrics.*.metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->input('metrics', []) as $index => $metric) {
                    $type = MetricType::tryFrom((string) ($metric['type'] ?? ''));
                    $name = (string) ($metric['name'] ?? '');

                    if ($type === null || ! MetricCatalog::isAllowed($type, $name)) {
                        $validator->errors()->add(
                            "metrics.{$index}.name",
                            'This metric type and name combination is not supported.',
                        );
                    }
                }
            },
        ];
    }
}
