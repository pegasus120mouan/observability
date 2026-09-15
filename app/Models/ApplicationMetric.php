<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ApplicationMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'application_id',
    'request_count',
    'error_count',
    'response_time_avg',
    'response_time_p95',
    'status_codes',
    'collected_at',
])]
class ApplicationMetric extends Model
{
    /** @use HasFactory<ApplicationMetricFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'request_count' => 0,
        'error_count' => 0,
        'response_time_avg' => 0,
        'response_time_p95' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_count' => 'integer',
            'error_count' => 'integer',
            'response_time_avg' => 'integer',
            'response_time_p95' => 'integer',
            'status_codes' => 'array',
            'collected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
