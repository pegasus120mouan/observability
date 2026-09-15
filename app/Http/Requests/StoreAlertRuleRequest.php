<?php

namespace App\Http\Requests;

use App\Enums\AlertCondition;
use App\Enums\AlertMetric;
use App\Enums\AlertSeverity;
use App\Models\AlertRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AlertRule::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'notification_channels' => $this->parsedChannels(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $metric = AlertMetric::tryFrom((string) $this->input('metric_type'));
        $needsThreshold = $metric?->usesThreshold() ?? true;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'metric_type' => ['required', Rule::enum(AlertMetric::class)],
            'condition' => [$needsThreshold ? 'required' : 'nullable', Rule::enum(AlertCondition::class)],
            'threshold' => [$needsThreshold ? 'required' : 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'duration' => ['required', 'integer', 'min:1', 'max:1440'],
            'severity' => ['required', Rule::enum(AlertSeverity::class)],
            'enabled' => ['sometimes', 'boolean'],
            'notification_channels' => ['nullable', 'array'],
            'notification_channels.*.channel' => ['required', 'in:mail,webhook'],
            'notification_channels.*.target' => ['required', 'string', 'max:2048'],
            'mail_targets' => ['nullable', 'string', 'max:2000'],
            'webhook_url' => ['nullable', 'url', 'max:2048'],
            'notify_mail' => ['sometimes', 'boolean'],
            'notify_webhook' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('notification_channels', []) as $index => $channel) {
                if (! is_array($channel)) {
                    continue;
                }

                $target = (string) ($channel['target'] ?? '');

                if (($channel['channel'] ?? '') === 'mail' && filter_var($target, FILTER_VALIDATE_EMAIL) === false) {
                    $validator->errors()->add('mail_targets', 'Each mail recipient must be a valid email address.');
                }

                if (($channel['channel'] ?? '') === 'webhook' && filter_var($target, FILTER_VALIDATE_URL) === false) {
                    $validator->errors()->add('webhook_url', 'The webhook URL is invalid.');
                }
            }
        });
    }

    /**
     * @return list<array{channel: string, target: string}>
     */
    private function parsedChannels(): array
    {
        $channels = [];

        if ($this->boolean('notify_mail')) {
            foreach (preg_split('/[\s,;]+/', (string) $this->input('mail_targets', '')) ?: [] as $email) {
                $email = trim($email);

                if ($email === '') {
                    continue;
                }

                $channels[] = ['channel' => 'mail', 'target' => $email];
            }
        }

        if ($this->boolean('notify_webhook') && $this->filled('webhook_url')) {
            $channels[] = ['channel' => 'webhook', 'target' => (string) $this->input('webhook_url')];
        }

        return $channels;
    }
}
