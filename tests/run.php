<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require_once $file;
}

$classes = array_filter(get_declared_classes(), static function (string $class): bool {
    return is_subclass_of($class, \Daybreak\Tests\TestCase::class);
});
sort($classes);

$passed = 0;
$failed = 0;

foreach ($classes as $class) {
    $reflection = new ReflectionClass($class);
    if ($reflection->isAbstract()) {
        continue;
    }

    $methods = array_filter(
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn(ReflectionMethod $method): bool => str_starts_with($method->getName(), 'test')
    );
    usort($methods, static fn(ReflectionMethod $left, ReflectionMethod $right): int => strcmp($left->getName(), $right->getName()));

    foreach ($methods as $method) {
        $case = $reflection->newInstance();
        $name = $reflection->getShortName() . '::' . $method->getName();

        try {
            $case->setUp();
            $method->invoke($case);
            $case->tearDown();
            $passed++;
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (\Throwable $throwable) {
            try {
                $case->tearDown();
            } catch (\Throwable) {
            }

            $failed++;
            fwrite(STDERR, "FAIL {$name}\n");
            fwrite(STDERR, '  ' . $throwable->getMessage() . "\n");
        }
    }
}

fwrite(STDOUT, "\n{$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
