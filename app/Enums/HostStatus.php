<?php

namespace App\Enums;

enum HostStatus: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Warning = 'warning';
    case Critical = 'critical';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Offline => 'Offline',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
            self::Maintenance => 'Maintenance',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Online => 'success',
            self::Offline => 'secondary',
            self::Warning => 'warning',
            self::Critical => 'danger',
            self::Maintenance => 'info',
        };
    }
}
