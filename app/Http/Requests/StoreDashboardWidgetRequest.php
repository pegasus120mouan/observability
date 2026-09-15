<?php

namespace App\Http\Requests;

use App\Enums\DashboardStatMetric;
use App\Enums\DashboardWidgetType;
use App\Enums\MetricType;
use App\Support\DashboardCatalog;
use App\Support\MetricCatalog;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDashboardWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $pair = (string) $this->input('metric_pair', '');

        if (str_contains($pair, '.')) {
            [$type, $name] = explode('.', $pair, 2);
            $this->merge([
                'metric_type' => $type,
                'metric_name' => $name,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = app(TenantContext::class)->id();

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DashboardWidgetType::class)],
            'width' => ['required', 'integer', Rule::in(DashboardCatalog::widths())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'stat_metric' => ['required_if:type,'.DashboardWidgetType::Stat->value, 'nullable', Rule::enum(DashboardStatMetric::class)],
            'metric_type' => ['required_if:type,'.DashboardWidgetType::Timeseries->value, 'nullable', Rule::enum(MetricType::class)],
            'metric_name' => ['nullable', 'string', 'max:64'],
            'range' => ['nullable', 'string', Rule::in(array_keys(DashboardCatalog::ranges()))],
            'host_id' => [
                'nullable',
                'integer',
                Rule::exists('hosts', 'id')->where(fn ($query) => $organizationId ? $query->where('organization_id', $organizationId) : $query),
            ],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
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

                if ($this->input('type') !== DashboardWidgetType::Timeseries->value) {
                    return;
                }

                $type = MetricType::tryFrom((string) $this->input('metric_type'));
                $name = (string) ($this->input('metric_name') ?: 'usage');

                if ($type === null || ! MetricCatalog::isAllowed($type, $name)) {
                    $validator->errors()->add('metric_name', 'This metric is not available for the selected type.');
                }
            },
        ];
    }
}
