<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Controller\StarController;
use ReflectionMethod;

final class StarControllerTest extends TestCase
{
    public function testMergeWidgetSlotsAppliesValidOverride(): void
    {
        $controller = new StarController();
        $method = new ReflectionMethod(StarController::class, 'mergeWidgetSlots');
        $method->setAccessible(true);

        $defaults = [
            1 => ['kind' => 'default_ransomlook'],
            2 => ['kind' => 'default_cves'],
        ];
        $overrides = [
            2 => ['kind' => 'custom', 'title' => 'Pinned Source'],
        ];

        $merged = $method->invoke($controller, $defaults, $overrides);

        $this->assertCount(2, $merged);
        $this->assertSame('default_ransomlook', $merged[0]['kind']);
        $this->assertSame('custom', $merged[1]['kind']);
    }

    public function testMergeWidgetSlotsIgnoresInvalidSlot(): void
    {
        $controller = new StarController();
        $method = new ReflectionMethod(StarController::class, 'mergeWidgetSlots');
        $method->setAccessible(true);

        $defaults = [
            1 => ['kind' => 'default_ransomlook'],
            2 => ['kind' => 'default_cves'],
        ];
        $overrides = [
            7 => ['kind' => 'custom', 'title' => 'Ignored'],
        ];

        $merged = $method->invoke($controller, $defaults, $overrides);

        $this->assertCount(2, $merged);
        $this->assertSame('default_ransomlook', $merged[0]['kind']);
        $this->assertSame('default_cves', $merged[1]['kind']);
    }
}
