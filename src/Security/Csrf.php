<?php
declare(strict_types=1);

namespace Daybreak\Security;

use RuntimeException;

/** Per-session CSRF token. Call check() right after the auth guard on every POST. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function check(): void
    {
        $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            http_response_code(419);
            throw new RuntimeException('CSRF token mismatch');
        }
    }
}
