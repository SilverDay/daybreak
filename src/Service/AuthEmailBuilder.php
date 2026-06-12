<?php

declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Config;
use RuntimeException;

final class AuthEmailBuilder
{
    public static function passwordResetBody(string $rawToken): string
    {
        $link = self::appUrl('/password/reset/' . rawurlencode($rawToken));

        return implode("\r\n", [
            'You requested a password reset for your Daybreak account.',
            '',
            'Open the link below to set a new password:',
            '',
            $link,
            '',
            'This link expires in 60 minutes and can only be used once.',
            '',
            'If you did not request a password reset, you can safely ignore this email.',
        ]);
    }

    public static function verificationBody(string $rawToken): string
    {
        $link = self::appUrl('/verify/' . rawurlencode($rawToken));

        return implode("\r\n", [
            'Welcome to Daybreak!',
            '',
            'Please verify your email address using the link below:',
            '',
            $link,
            '',
            'This link expires in 24 hours.',
            '',
            'If you did not register for Daybreak, you can safely ignore this email.',
        ]);
    }

    public static function appUrl(string $path): string
    {
        $base = trim((string) (getenv('APP_BASE_URL') ?: Config::get('APP_BASE_URL', 'https://daybreak.silverday.de')));
        $base = str_replace(["\r", "\n"], '', $base);
        $parts = parse_url($base);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('APP_BASE_URL must be an absolute http/https URL');
        }
        if (!in_array($parts['scheme'], ['http', 'https'], true)) {
            throw new RuntimeException('APP_BASE_URL must use http or https');
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        return rtrim($origin, '/') . '/' . ltrim($path, '/');
    }
}
