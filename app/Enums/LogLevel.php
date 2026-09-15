<?php

namespace App\Enums;

enum LogLevel: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';

    public function label(): string
    {
        return strtoupper($this->value);
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Debug, self::Info => 'secondary',
            self::Notice => 'info',
            self::Warning => 'warning',
            self::Error, self::Critical, self::Alert, self::Emergency => 'danger',
        };
    }

    public function isProblem(): bool
    {
        return in_array($this, [self::Error, self::Critical, self::Alert, self::Emergency], true);
    }
}
