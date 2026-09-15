<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Pending = 'pending';
    case Online = 'online';
    case Offline = 'offline';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Revoked => 'Revoked',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Online => 'success',
            self::Offline => 'warning',
            self::Revoked => 'danger',
        };
    }
}
