<?php

namespace App\Models;

use App\Enums\DashboardWidgetType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\DashboardWidgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'dashboard_id',
    'type',
    'title',
    'sort_order',
    'width',
    'config',
])]
class DashboardWidget extends Model
{
    /** @use HasFactory<DashboardWidgetFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'width' => 6,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DashboardWidgetType::class,
            'sort_order' => 'integer',
            'width' => 'integer',
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    public function configInt(string $key, int $default): int
    {
        $value = $this->config[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function configString(string $key, ?string $default = null): ?string
    {
        $value = $this->config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
