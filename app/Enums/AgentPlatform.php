<?php

namespace App\Enums;

enum AgentPlatform: string
{
    case Linux = 'linux';
    case Windows = 'windows';

    public function label(): string
    {
        return match ($this) {
            self::Linux => 'Linux',
            self::Windows => 'Windows',
        };
    }
}
