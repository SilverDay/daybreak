<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Security\SsrfGuard;
use RuntimeException;

final class SsrfGuardTest extends TestCase
{
    /** @var list<string> */
    private const BLOCKED_IPS = [
        '::ffff:127.0.0.1',
        '::ffff:169.254.169.254',
        '0:0:0:0:0:ffff:7f00:1',
        '100.64.0.1',
        '100.100.100.200',
        '198.18.0.1',
        '192.0.0.192',
        '127.0.0.1',
        '10.0.0.5',
        '192.168.1.1',
        '169.254.169.254',
        '::1',
        'fe80::1',
        'fd00::1',
    ];

    /** @var list<string> */
    private const ALLOWED_IPS = [
        '8.8.8.8',
        '1.1.1.1',
        '93.184.216.34',
        '2001:4860:4860::8888',
        '::ffff:8.8.8.8',
    ];

    public function testRejectsBlockedLiteralIps(): void
    {
        foreach (self::BLOCKED_IPS as $ip) {
            $url = $this->literalUrl($ip);
            $this->expectException(
                RuntimeException::class,
                static fn() => SsrfGuard::assertSafe($url),
                'Expected blocked IP to be rejected: ' . $ip
            );
        }
    }

    public function testAllowsPublicLiteralIps(): void
    {
        foreach (self::ALLOWED_IPS as $ip) {
            $pin = SsrfGuard::assertSafe($this->literalUrl($ip));
            $this->assertSame(80, $pin['port']);
            $this->assertSame('http', $pin['scheme']);
            $this->assertTrue($pin['host'] !== '');
            $this->assertTrue($pin['ip'] !== '');
        }
    }

    private function literalUrl(string $ip): string
    {
        if (str_contains($ip, ':')) {
            return 'http://[' . $ip . ']/feed.xml';
        }

        return 'http://' . $ip . '/feed.xml';
    }
}
