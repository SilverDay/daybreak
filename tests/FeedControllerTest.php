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

    public function testMergeWidgetSlotsAppliesValidOverride(): void
    {
        $controller = new FeedController();
        $method = new ReflectionMethod(FeedController::class, 'mergeWidgetSlots');
        $method->setAccessible(true);

        $defaults = [
            1 => ['kind' => 'default_ransomlook'],
            2 => ['kind' => 'default_cves'],
        ];
        $overrides = [
            1 => ['kind' => 'custom', 'title' => 'My Source'],
        ];

        $merged = $method->invoke($controller, $defaults, $overrides);

        $this->assertCount(2, $merged);
        $this->assertSame('custom', $merged[0]['kind']);
        $this->assertSame('default_cves', $merged[1]['kind']);
    }

    public function testMergeWidgetSlotsIgnoresInvalidSlotOverride(): void
    {
        $controller = new FeedController();
        $method = new ReflectionMethod(FeedController::class, 'mergeWidgetSlots');
        $method->setAccessible(true);

        $defaults = [
            1 => ['kind' => 'default_ransomlook'],
            2 => ['kind' => 'default_cves'],
        ];
        $overrides = [
            3 => ['kind' => 'custom', 'title' => 'Ignored'],
        ];

        $merged = $method->invoke($controller, $defaults, $overrides);

        $this->assertCount(2, $merged);
        $this->assertSame('default_ransomlook', $merged[0]['kind']);
        $this->assertSame('default_cves', $merged[1]['kind']);
    }
}
