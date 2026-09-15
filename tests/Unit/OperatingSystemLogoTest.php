<?php

namespace Tests\Unit;

use App\Support\OperatingSystemLogo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OperatingSystemLogoTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: string, 2: string}>
     */
    public static function operatingSystems(): array
    {
        return [
            'ubuntu' => ['Ubuntu', 'ubuntu', 'bi-ubuntu'],
            'ubuntu version' => ['Ubuntu 24.04', 'ubuntu', 'bi-ubuntu'],
            'debian' => ['Debian GNU/Linux', 'debian', 'bi-tux'],
            'windows server' => ['Windows Server', 'windows', 'bi-windows'],
            'windows family' => ['Windows', 'windows', 'bi-windows'],
            'linux' => ['Linux', 'linux', 'bi-tux'],
            'macos' => ['Darwin', 'macos', 'bi-apple'],
            'rhel' => ['Red Hat Enterprise Linux', 'redhat', 'bi-tux'],
            'empty' => [null, 'unknown', 'bi-pc-display'],
            'blank' => ['  ', 'unknown', 'bi-pc-display'],
        ];
    }

    #[DataProvider('operatingSystems')]
    public function test_resolve_maps_operating_system_names_to_a_logo_family(?string $operatingSystem, string $family, string $icon): void
    {
        $logo = OperatingSystemLogo::resolve($operatingSystem);

        $this->assertSame($family, $logo['family']);
        $this->assertSame($icon, $logo['icon']);
    }
}
