<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use Daybreak\Router;

final class RouterTestController
{
    public static array $lastArgs = [];
    public static int $calls = 0;

    public function show(array $args = []): void
    {
        self::$lastArgs = $args;
        self::$calls++;
        echo 'ok';
    }
}

final class RouterTest extends TestCase
{
    public function setUp(): void
    {
        RouterTestController::$lastArgs = [];
        RouterTestController::$calls = 0;
        http_response_code(200);
    }

    public function tearDown(): void
    {
        http_response_code(200);
    }

    public function testDispatchMatchesDynamicSegments(): void
    {
        $router = new Router();
        $router->get('/feed/{slug}', [RouterTestController::class, 'show']);

        ob_start();
        $router->dispatch('GET', '/feed/security/');
        $output = ob_get_clean();

        $this->assertSame('ok', $output);
        $this->assertSame(['slug' => 'security'], RouterTestController::$lastArgs);
        $this->assertSame(1, RouterTestController::$calls);
    }

    public function testDispatchReturns404WhenNoRouteMatches(): void
    {
        $router = new Router();

        ob_start();
        $router->dispatch('GET', '/missing');
        $output = ob_get_clean();

        $this->assertSame('Not found', $output);
        $this->assertSame(404, http_response_code());
    }
}
