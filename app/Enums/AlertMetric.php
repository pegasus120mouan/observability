<?php

namespace App\Enums;

enum AlertMetric: string
{
    case Cpu = 'cpu';
    case Memory = 'memory';
    case Disk = 'disk';
    case HostOffline = 'host_offline';
    case LogError = 'log_error';

    public function label(): string
    {
        return match ($this) {
            self::Cpu => 'CPU usage',
            self::Memory => 'Memory usage',
            self::Disk => 'Disk usage',
            self::HostOffline => 'Host offline',
            self::LogError => 'Error log count',
        };
    }

    public function usesThreshold(): bool
    {
        return $this !== self::HostOffline;
    }

    public function metricType(): ?MetricType
    {
        return match ($this) {
            self::Cpu => MetricType::Cpu,
            self::Memory => MetricType::Memory,
            self::Disk => MetricType::Disk,
            default => null,
        };
    }

    public function metricName(): string
    {
        return 'usage';
    }

    public function unit(): string
    {
        return match ($this) {
            self::Cpu, self::Memory, self::Disk => '%',
            self::HostOffline => 'minutes',
            self::LogError => 'events',
        };
    }
}
