<?php

namespace App\Enums;

enum AlertCondition: string
{
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';

    public function label(): string
    {
        return match ($this) {
            self::Gt => 'greater than',
            self::Gte => 'greater than or equal to',
            self::Lt => 'less than',
            self::Lte => 'less than or equal to',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Gt => '>',
            self::Gte => '>=',
            self::Lt => '<',
            self::Lte => '<=',
        };
    }

    public function matches(float $value, float $threshold): bool
    {
        return match ($this) {
            self::Gt => $value > $threshold,
            self::Gte => $value >= $threshold,
            self::Lt => $value < $threshold,
            self::Lte => $value <= $threshold,
        };
    }
}
