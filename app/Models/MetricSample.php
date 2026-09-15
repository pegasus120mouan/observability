<?php

namespace App\Models;

use App\Enums\MetricType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\MetricSampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'host_id',
    'metric_type',
    'metric_name',
    'value',
    'unit',
    'collected_at',
    'metadata',
])]
class MetricSample extends Model
{
    /** @use HasFactory<MetricSampleFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_type' => MetricType::class,
            'value' => 'float',
            'collected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
