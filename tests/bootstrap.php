<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require __DIR__ . '/TestCase.php';
foreach (glob(__DIR__ . '/Fake*.php') ?: [] as $file) {
    require_once $file;
}
