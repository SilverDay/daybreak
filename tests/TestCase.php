<?php

declare(strict_types=1);

namespace Daybreak\Tests;

use RuntimeException;

abstract class TestCase
{
    public function setUp(): void {}

    public function tearDown(): void {}

    protected function fail(string $message): never
    {
        throw new RuntimeException($message);
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail($message !== '' ? $message : sprintf(
                "Failed asserting that %s is identical to %s.",
                $this->export($actual),
                $this->export($expected)
            ));
        }
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            $this->fail($message !== '' ? $message : 'Failed asserting that condition is true.');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition) {
            $this->fail($message !== '' ? $message : 'Failed asserting that condition is false.');
        }
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        if ($value !== null) {
            $this->fail($message !== '' ? $message : 'Failed asserting that value is null.');
        }
    }

    protected function assertCount(int $expectedCount, array|\Countable $items, string $message = ''): void
    {
        $actualCount = count($items);
        if ($expectedCount !== $actualCount) {
            $this->fail($message !== '' ? $message : sprintf(
                'Failed asserting count %d, got %d.',
                $expectedCount,
                $actualCount
            ));
        }
    }

    protected function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        if (!array_key_exists($key, $array)) {
            $this->fail($message !== '' ? $message : sprintf('Failed asserting that array has key %s.', $this->export($key)));
        }
    }

    protected function expectException(string $exceptionClass, callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (\Throwable $throwable) {
            if ($throwable instanceof $exceptionClass) {
                return;
            }

            $this->fail($message !== '' ? $message : sprintf(
                'Failed asserting exception of type %s, got %s.',
                $exceptionClass,
                $throwable::class
            ));
        }

        $this->fail($message !== '' ? $message : sprintf(
            'Failed asserting that exception of type %s was thrown.',
            $exceptionClass
        ));
    }

    private function export(mixed $value): string
    {
        return var_export($value, true);
    }
}
