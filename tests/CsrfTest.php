<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Security\Csrf;
use RuntimeException;

final class CsrfTest extends TestCase
{
    public function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    public function testCheckRejectsMissingSessionTokenEvenWithEmptySubmittedToken(): void
    {
        $_POST['_csrf'] = '';

        $this->expectException(RuntimeException::class, static function (): void {
            Csrf::check();
        });
    }

    public function testCheckAcceptsMatchingToken(): void
    {
        $token = Csrf::token();
        $_POST['_csrf'] = $token;

        Csrf::check();
        $this->assertTrue(true);
    }

    public function testCheckRejectsEmptySubmittedTokenWhenSessionTokenExists(): void
    {
        Csrf::token();
        $_POST['_csrf'] = '';

        $this->expectException(RuntimeException::class, static function (): void {
            Csrf::check();
        });
    }

    public function testCheckAcceptsHeaderToken(): void
    {
        $token = Csrf::token();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        Csrf::check();
        $this->assertTrue(true);
    }
}
