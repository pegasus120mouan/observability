<?php

namespace App\Enums;

enum ApmSpanKind: string
{
    case Server = 'server';
    case Client = 'client';
    case Producer = 'producer';
    case Consumer = 'consumer';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Server => 'Server',
            self::Client => 'Client',
            self::Producer => 'Producer',
            self::Consumer => 'Consumer',
            self::Internal => 'Internal',
        };
    }
}
