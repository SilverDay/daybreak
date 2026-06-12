<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Service\AuthEmailBuilder;

final class AuthEmailBuilderTest extends TestCase
{
    public function setUp(): void
    {
        putenv('APP_BASE_URL=https://daybreak.example.test');
    }

    public function tearDown(): void
    {
        putenv('APP_BASE_URL');
    }

    public function testAppUrlUsesValidatedConfiguredOrigin(): void
    {
        $this->assertSame(
            'https://daybreak.example.test/verify/token123',
            \Daybreak\Service\AuthEmailBuilder::appUrl('/verify/token123')
        );
    }

    public function testPasswordResetBodyContainsOnlyExpectedLink(): void
    {
        $body = \Daybreak\Service\AuthEmailBuilder::passwordResetBody('abc123');

        $this->assertTrue(str_contains($body, 'https://daybreak.example.test/password/reset/abc123'));
        $this->assertFalse(str_contains($body, "\r\n\r\n\r\n"));
    }

    public function testVerificationBodyContainsExpectedLink(): void
    {
        $body = \Daybreak\Service\AuthEmailBuilder::verificationBody('token-xyz');

        $this->assertTrue(str_contains($body, 'https://daybreak.example.test/verify/token-xyz'));
    }
}
