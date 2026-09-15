<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Mitigated = 'mitigated';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Investigating => 'Investigating',
            self::Mitigated => 'Mitigated',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Investigating => 'warning',
            self::Mitigated => 'info',
            self::Resolved => 'success',
            self::Closed => 'secondary',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Open => 0,
            self::Investigating => 1,
            self::Mitigated => 2,
            self::Resolved => 3,
            self::Closed => 4,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Investigating, self::Mitigated], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return $next->rank() >= $this->rank();
    }
}
