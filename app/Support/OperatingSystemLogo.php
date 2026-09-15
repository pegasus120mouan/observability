<?php

namespace App\Support;

final class OperatingSystemLogo
{
    /**
     * @return array{family: string, icon: string, label: string}
     */
    public static function resolve(?string $operatingSystem): array
    {
        $normalized = mb_strtolower(trim((string) $operatingSystem));

        if ($normalized === '') {
            return self::mark('unknown', 'bi-pc-display', 'Unknown');
        }

        return match (true) {
            str_contains($normalized, 'ubuntu') => self::mark('ubuntu', 'bi-ubuntu', 'Ubuntu'),
            str_contains($normalized, 'debian') => self::mark('debian', 'bi-tux', 'Debian'),
            str_contains($normalized, 'fedora') => self::mark('fedora', 'bi-tux', 'Fedora'),
            self::containsAny($normalized, ['red hat', 'redhat', 'rhel', 'centos', 'rocky', 'alma']) => self::mark('redhat', 'bi-tux', 'Red Hat'),
            self::containsAny($normalized, ['suse', 'sles']) => self::mark('suse', 'bi-tux', 'SUSE'),
            str_contains($normalized, 'amazon') => self::mark('amazon', 'bi-tux', 'Amazon Linux'),
            str_contains($normalized, 'alpine') => self::mark('alpine', 'bi-tux', 'Alpine'),
            str_contains($normalized, 'arch') => self::mark('arch', 'bi-tux', 'Arch'),
            self::containsAny($normalized, ['darwin', 'macos', 'mac os', 'os x', 'osx']) => self::mark('macos', 'bi-apple', 'macOS'),
            str_contains($normalized, 'windows') => self::mark('windows', 'bi-windows', 'Windows'),
            self::containsAny($normalized, ['linux', 'unix']) => self::mark('linux', 'bi-tux', 'Linux'),
            default => self::mark('unknown', 'bi-pc-display', $operatingSystem ?? 'Unknown'),
        };
    }

    /**
     * @param  list<string>  $needles
     */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{family: string, icon: string, label: string}
     */
    private static function mark(string $family, string $icon, string $label): array
    {
        return [
            'family' => $family,
            'icon' => $icon,
            'label' => $label,
        ];
    }
}
