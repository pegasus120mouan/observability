<?php

namespace App\Support;

use App\Support\GeoIp\CountryCatalog;

/**
 * IPv4 country lookup uses a /16 table packed from the public-domain
 * geo-whois-asn-country dataset: https://github.com/sapics/ip-location-db
 */
final class GeoIpLocator
{
    private static ?string $prefixes = null;

    /**
     * @param  array{lat: float, lng: float, country: ?string, label: string}|null  $origin
     * @return array{
     *     country: ?string,
     *     city: ?string,
     *     lat: ?float,
     *     lng: ?float,
     *     label: string,
     *     private: bool
     * }|null
     */
    public function locate(?string $ip, ?array $origin = null): ?array
    {
        $ip = trim((string) $ip);

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if ($this->isPrivate($ip)) {
            return [
                'country' => $origin['country'] ?? null,
                'city' => 'Private network',
                'lat' => $origin['lat'] ?? null,
                'lng' => $origin['lng'] ?? null,
                'label' => $origin['label'] ?? 'Private network',
                'private' => true,
            ];
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        $country = $this->countryFromIpv4($ip);

        if ($country === null) {
            return null;
        }

        $place = CountryCatalog::find($country);

        if ($place === null) {
            return [
                'country' => $country,
                'city' => null,
                'lat' => null,
                'lng' => null,
                'label' => $country,
                'private' => false,
            ];
        }

        [$lat, $lng] = $this->offset($ip, $place['lat'], $place['lng']);

        return [
            'country' => $country,
            'city' => null,
            'lat' => $lat,
            'lng' => $lng,
            'label' => $place['name'],
            'private' => false,
        ];
    }

    public function isPrivate(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * @return list{float, float}
     */
    private function offset(string $ip, float $lat, float $lng): array
    {
        $hash = crc32($ip);

        return [
            round($lat + ((($hash % 801) / 800) - 0.5) * 1.6, 4),
            round($lng + (((($hash >> 10) % 801) / 800) - 0.5) * 2.0, 4),
        ];
    }

    private function countryFromIpv4(string $ip): ?string
    {
        $binary = inet_pton($ip);

        if ($binary === false || strlen($binary) !== 4) {
            return null;
        }

        $long = unpack('N', $binary)[1];
        $prefixes = $this->prefixes();
        $offset = intdiv($long, 65536) * 2;

        if ($offset < 0 || $offset + 1 >= strlen($prefixes)) {
            return null;
        }

        $code = substr($prefixes, $offset, 2);

        if ($code === "\0\0" || ! preg_match('/^[A-Z]{2}$/', $code)) {
            return null;
        }

        return $code;
    }

    private function prefixes(): string
    {
        if (self::$prefixes !== null) {
            return self::$prefixes;
        }

        $path = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'geo'.DIRECTORY_SEPARATOR.'ipv4-country-16.bin';
        $contents = is_file($path) ? file_get_contents($path) : false;
        self::$prefixes = is_string($contents) ? $contents : str_repeat("\0", 131072);

        return self::$prefixes;
    }
}
