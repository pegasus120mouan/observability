<?php

namespace App\Support;

use App\Enums\MetricType;

final class MetricCatalog
{
    /**
     * @return array<string, array{unit: string, label: string}>
     */
    public static function namesFor(MetricType $type): array
    {
        return match ($type) {
            MetricType::Cpu => [
                'usage' => ['unit' => 'percent', 'label' => 'CPU usage'],
            ],
            MetricType::Memory => [
                'usage' => ['unit' => 'percent', 'label' => 'Memory usage'],
                'used_bytes' => ['unit' => 'bytes', 'label' => 'Memory used'],
                'total_bytes' => ['unit' => 'bytes', 'label' => 'Memory total'],
            ],
            MetricType::Disk => [
                'usage' => ['unit' => 'percent', 'label' => 'Disk usage'],
                'used_bytes' => ['unit' => 'bytes', 'label' => 'Disk used'],
                'total_bytes' => ['unit' => 'bytes', 'label' => 'Disk total'],
            ],
            MetricType::Network => [
                'rx_bytes' => ['unit' => 'bytes', 'label' => 'Network received'],
                'tx_bytes' => ['unit' => 'bytes', 'label' => 'Network sent'],
            ],
            MetricType::Load => [
                'load1' => ['unit' => 'load', 'label' => 'Load average (1m)'],
            ],
            MetricType::Process => [
                'count' => ['unit' => 'count', 'label' => 'Process count'],
            ],
            MetricType::Uptime => [
                'seconds' => ['unit' => 'seconds', 'label' => 'Uptime'],
            ],
        };
    }

    public static function isAllowed(MetricType $type, string $name): bool
    {
        return array_key_exists($name, self::namesFor($type));
    }

    public static function unit(MetricType $type, string $name): string
    {
        return self::namesFor($type)[$name]['unit'] ?? 'count';
    }

    public static function label(MetricType $type, string $name): string
    {
        return self::namesFor($type)[$name]['label'] ?? $name;
    }

    /**
     * @return list<array{type: MetricType, name: string, label: string}>
     */
    public static function chartable(): array
    {
        return [
            ['type' => MetricType::Cpu, 'name' => 'usage', 'label' => 'CPU usage'],
            ['type' => MetricType::Memory, 'name' => 'usage', 'label' => 'Memory usage'],
            ['type' => MetricType::Disk, 'name' => 'usage', 'label' => 'Disk usage'],
            ['type' => MetricType::Network, 'name' => 'rx_bytes', 'label' => 'Network received'],
            ['type' => MetricType::Network, 'name' => 'tx_bytes', 'label' => 'Network sent'],
            ['type' => MetricType::Load, 'name' => 'load1', 'label' => 'Load average'],
            ['type' => MetricType::Uptime, 'name' => 'seconds', 'label' => 'Uptime'],
        ];
    }
}
