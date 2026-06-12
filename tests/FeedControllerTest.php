<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Controller\FeedController;
use ReflectionMethod;

final class FeedControllerTest extends TestCase
{
    public function testSafeReturnPathAllowsLocalPath(): void
    {
        $controller = new FeedController();
        $method = new ReflectionMethod(FeedController::class, 'safeReturnPath');
        $method->setAccessible(true);

        $safe = $method->invoke($controller, '/feed/category/privacy?days=since');

        $this->assertSame('/feed/category/privacy?days=since', $safe);
    }

    public function testSafeReturnPathRejectsAbsoluteUrl(): void
    {
        $controller = new FeedController();
        $method = new ReflectionMethod(FeedController::class, 'safeReturnPath');
        $method->setAccessible(true);

        $safe = $method->invoke($controller, 'https://evil.example/phish');

        $this->assertSame('/feed?days=since', $safe);
    }

    public function testSafeReturnPathRejectsProtocolRelativeUrl(): void
    {
        $controller = new FeedController();
        $method = new ReflectionMethod(FeedController::class, 'safeReturnPath');
        $method->setAccessible(true);

        $safe = $method->invoke($controller, '//evil.example/phish');

        $this->assertSame('/feed?days=since', $safe);
    }

    public function testSafeReturnPathRejectsNonLocalPath(): void
    {
        $controller = new FeedController();
        $method = new ReflectionMethod(FeedController::class, 'safeReturnPath');
        $method->setAccessible(true);

        $safe = $method->invoke($controller, 'feed?days=since');

        $this->assertSame('/feed?days=since', $safe);
    }
}
