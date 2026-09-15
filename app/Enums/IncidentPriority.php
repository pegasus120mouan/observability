<?php

namespace App\Enums;

enum IncidentPriority: string
{
    case P1 = 'p1';
    case P2 = 'p2';
    case P3 = 'p3';
    case P4 = 'p4';

    public function label(): string
    {
        return match ($this) {
            self::P1 => 'P1',
            self::P2 => 'P2',
            self::P3 => 'P3',
            self::P4 => 'P4',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::P1 => 'danger',
            self::P2 => 'warning',
            self::P3 => 'info',
            self::P4 => 'secondary',
        };
    }

    public static function fromSeverity(AlertSeverity $severity): self
    {
        return match ($severity) {
            AlertSeverity::Critical => self::P1,
            AlertSeverity::High => self::P2,
            AlertSeverity::Medium => self::P3,
            AlertSeverity::Low => self::P4,
        };
    }
}
