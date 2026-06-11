<?php
declare(strict_types=1);

namespace Daybreak\Security;

/** Send security headers on every web response. Called from the front controller. */
final class SecurityHeaders
{
    public static function send(): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
            . "style-src 'self'; script-src 'self'; object-src 'none'; "
            . "base-uri 'self'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}
