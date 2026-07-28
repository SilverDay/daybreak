<?php

declare(strict_types=1);

namespace Daybreak\Security;

/** Send security headers on every web response. Called from the front controller. */
final class SecurityHeaders
{
    private static string $nonce = '';

    /** CSP nonce for the anti-FOUC inline script. Available after send(). */
    public static function nonce(): string
    {
        return self::$nonce;
    }

    public static function send(): void
    {
        self::$nonce = bin2hex(random_bytes(16));
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
            . "style-src 'self'; script-src 'self' 'nonce-" . self::$nonce . "'; object-src 'none'; "
            . "base-uri 'self'; frame-ancestors 'none'; form-action 'self'; frame-src 'none'; upgrade-insecure-requests");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        if (self::shouldSendNoIndexHeader($path)) {
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
        // HSTS: 1 year, apply to subdomains. Only effective over HTTPS.
        if (($_SERVER['HTTPS'] ?? '') === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    private static function shouldSendNoIndexHeader(string $path): bool
    {
        $noIndexPrefixes = [
            '/admin',
            '/feed',
            '/settings',
            '/password',
            '/login',
            '/register',
            '/verify',
            '/search',
            '/suggest',
        ];

        foreach ($noIndexPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
