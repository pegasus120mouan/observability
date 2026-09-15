<?php

namespace App\Enums;

enum HostEnvironment: string
{
    case Production = 'production';
    case Staging = 'staging';
    case Development = 'development';
    case Test = 'test';

    public function label(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Staging => 'Staging',
            self::Development => 'Development',
            self::Test => 'Test',
        };
    }
}
