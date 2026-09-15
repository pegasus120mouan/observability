<?php

namespace App\Models;

use App\Enums\AlertCondition;
use App\Enums\AlertMetric;
use App\Enums\AlertSeverity;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\AlertRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'name',
    'description',
    'metric_type',
    'condition',
    'threshold',
    'duration',
    'severity',
    'enabled',
    'notification_channels',
])]
class AlertRule extends Model
{
    /** @use HasFactory<AlertRuleFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'condition' => 'gt',
        'duration' => 5,
        'severity' => 'high',
        'enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_type' => AlertMetric::class,
            'condition' => AlertCondition::class,
            'threshold' => 'float',
            'duration' => 'integer',
            'severity' => AlertSeverity::class,
            'enabled' => 'boolean',
            'notification_channels' => 'array',
        ];
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function summary(): string
    {
        if ($this->metric_type === AlertMetric::HostOffline) {
            return 'Host offline for '.$this->duration.' minutes';
        }

        $threshold = rtrim(rtrim(number_format((float) $this->threshold, 2, '.', ''), '0'), '.');

        return $this->metric_type->label().' '.$this->condition->symbol().' '.$threshold.$this->metric_type->unit()
            .' for '.$this->duration.' minutes';
    }

    /**
     * @return list<array{channel: string, target: string}>
     */
    public function channels(): array
    {
        $channels = [];

        foreach ($this->notification_channels ?? [] as $channel) {
            if (! is_array($channel) || ! isset($channel['channel'], $channel['target'])) {
                continue;
            }

            $target = trim((string) $channel['target']);

            if ($target === '') {
                continue;
            }

            $channels[] = [
                'channel' => (string) $channel['channel'],
                'target' => $target,
            ];
        }

        return $channels;
    }
}
