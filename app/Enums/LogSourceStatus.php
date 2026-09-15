<?php

namespace App\Enums;

enum LogSourceStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Paused => 'Paused',
            self::Error => 'Error',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'secondary',
            self::Error => 'danger',
        };
    }
}
