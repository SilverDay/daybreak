<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Controller\UserController;
use ReflectionMethod;

final class UserControllerTest extends TestCase
{
    public function testParseOptionalPositiveIntAcceptsEmptyAsNull(): void
    {
        $controller = new UserController();
        $method = new ReflectionMethod(UserController::class, 'parseOptionalPositiveInt');
        $method->setAccessible(true);

        $parsed = $method->invoke($controller, '');

        $this->assertNull($parsed);
    }

    public function testParseOptionalPositiveIntRejectsNonDigits(): void
    {
        $controller = new UserController();
        $method = new ReflectionMethod(UserController::class, 'parseOptionalPositiveInt');
        $method->setAccessible(true);

        $parsed = $method->invoke($controller, 'abc');

        $this->assertSame(-1, $parsed);
    }

    public function testValidateWidgetSelectionRejectsDuplicateSources(): void
    {
        $controller = new UserController();
        $method = new ReflectionMethod(UserController::class, 'validateWidgetSelection');
        $method->setAccessible(true);

        $error = $method->invoke($controller, 42, 42, [42, 43]);

        $this->assertSame('Choose different sources for slot 1 and slot 2.', $error);
    }

    public function testValidateWidgetSelectionRejectsIneligibleSource(): void
    {
        $controller = new UserController();
        $method = new ReflectionMethod(UserController::class, 'validateWidgetSelection');
        $method->setAccessible(true);

        $error = $method->invoke($controller, 99, null, [42, 43]);

        $this->assertSame('Slot 1 source is no longer eligible.', $error);
    }

    public function testValidateWidgetSelectionAcceptsDistinctEligibleSources(): void
    {
        $controller = new UserController();
        $method = new ReflectionMethod(UserController::class, 'validateWidgetSelection');
        $method->setAccessible(true);

        $error = $method->invoke($controller, 42, 43, [42, 43, 44]);

        $this->assertNull($error);
    }
}
