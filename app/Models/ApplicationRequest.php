<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ApplicationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'application_id',
    'occurred_at',
    'method',
    'resource',
    'status_code',
    'duration_us',
    'client_ip',
    'geo_country',
    'geo_city',
    'geo_lat',
    'geo_lng',
])]
class ApplicationRequest extends Model
{
    /** @use HasFactory<ApplicationRequestFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'duration_us' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'status_code' => 'integer',
            'duration_us' => 'integer',
            'geo_lat' => 'float',
            'geo_lng' => 'float',
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
