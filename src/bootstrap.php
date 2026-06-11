<?php
declare(strict_types=1);

/**
 * Daybreak bootstrap: autoloader, config, error handling.
 * Included by public/index.php and bin/fetch.php.
 */

define('DB_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Daybreak\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $path = DB_ROOT . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

\Daybreak\Config::load(DB_ROOT . '/config/.env');

error_reporting(E_ALL);
ini_set('display_errors', \Daybreak\Config::get('APP_DEBUG') === 'true' ? '1' : '0');
date_default_timezone_set('UTC');
