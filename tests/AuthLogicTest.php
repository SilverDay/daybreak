<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\AuthLogic;

final class AuthLogicTest extends TestCase
{
    public function testNormalizeEmailLowercasesAndTrims(): void
    {
        $this->assertSame('user@example.com', AuthLogic::normalizeEmail('  User@Example.COM  '));
    }

    public function testSanitizeEmailHeaderRemovesHeaderBreaks(): void
    {
        $this->assertSame('user@example.combcc:evil@example.com', AuthLogic::sanitizeEmailHeader("User@example.com\r\nBcc:evil@example.com"));
    }

    public function testNormalizeDisplayNameTrimsAndLimitsLength(): void
    {
        $name = AuthLogic::normalizeDisplayName('  ' . str_repeat('a', 100) . '  ');

        $this->assertSame(80, mb_strlen($name));
        $this->assertSame(str_repeat('a', 80), $name);
    }

    public function testPasswordValidityUsesMinimumLength(): void
    {
        $this->assertFalse(AuthLogic::isPasswordValid('short-pass'));
        $this->assertTrue(AuthLogic::isPasswordValid('long-enough-1'));
    }

    public function testClampWindowDaysStaysWithinBounds(): void
    {
        $this->assertSame(1, AuthLogic::clampWindowDays(0));
        $this->assertSame(7, AuthLogic::clampWindowDays(7));
        $this->assertSame(30, AuthLogic::clampWindowDays(99));
    }

    public function testShouldThrottleUsesEitherThreshold(): void
    {
        $this->assertFalse(AuthLogic::shouldThrottle(9, 4));
        $this->assertTrue(AuthLogic::shouldThrottle(10, 0));
        $this->assertTrue(AuthLogic::shouldThrottle(0, 5));
    }

    public function testTokenHashIsStableSha256(): void
    {
        $hash = AuthLogic::tokenHash('abc123');

        $this->assertSame(hash('sha256', 'abc123'), $hash);
        $this->assertSame(64, strlen($hash));
    }
}
