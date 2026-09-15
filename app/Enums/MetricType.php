<?php

namespace App\Enums;

enum MetricType: string
{
    case Cpu = 'cpu';
    case Memory = 'memory';
    case Disk = 'disk';
    case Network = 'network';
    case Load = 'load';
    case Process = 'process';
    case Uptime = 'uptime';

    public function label(): string
    {
        return match ($this) {
            self::Cpu => 'CPU',
            self::Memory => 'Memory',
            self::Disk => 'Disk',
            self::Network => 'Network',
            self::Load => 'Load',
            self::Process => 'Processes',
            self::Uptime => 'Uptime',
        };
    }
}
