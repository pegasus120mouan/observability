<?php

namespace Tests\Unit\Support;

use App\Support\GeoIpLocator;
use PHPUnit\Framework\TestCase;

class GeoIpLocatorTest extends TestCase
{
    public function test_loopback_is_treated_as_private_and_uses_the_origin(): void
    {
        $located = (new GeoIpLocator)->locate('127.0.0.1', [
            'lat' => 46.2,
            'lng' => 2.2,
            'country' => 'FR',
            'label' => 'France',
        ]);

        $this->assertNotNull($located);
        $this->assertTrue($located['private']);
        $this->assertSame('Private network', $located['city']);
        $this->assertSame(46.2, $located['lat']);
        $this->assertSame(2.2, $located['lng']);
        $this->assertSame('FR', $located['country']);
    }

    public function test_google_dns_resolves_to_the_united_states(): void
    {
        $located = (new GeoIpLocator)->locate('8.8.8.8');

        $this->assertNotNull($located);
        $this->assertFalse($located['private']);
        $this->assertSame('US', $located['country']);
        $this->assertSame('United States', $located['label']);
        $this->assertIsFloat($located['lat']);
        $this->assertIsFloat($located['lng']);
    }

    public function test_invalid_ip_returns_null(): void
    {
        $this->assertNull((new GeoIpLocator)->locate('not-an-ip'));
        $this->assertNull((new GeoIpLocator)->locate(''));
    }
}
