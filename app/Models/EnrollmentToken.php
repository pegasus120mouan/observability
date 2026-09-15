<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\AgentCredentials;
use Database\Factories\EnrollmentTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'created_by',
    'name',
    'token_hash',
    'expires_at',
    'last_used_at',
    'revoked_at',
])]
#[Hidden(['token_hash'])]
class EnrollmentToken extends Model
{
    /** @use HasFactory<EnrollmentTokenFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public static function findUsableByPlainText(string $plainText): ?self
    {
        $token = static::query()
            ->withoutGlobalScopes()
            ->with('organization')
            ->where('token_hash', AgentCredentials::hash($plainText))
            ->first();

        if ($token === null || ! $token->isUsable()) {
            return null;
        }

        return $token;
    }
}
