<?php

declare(strict_types=1);

namespace Daybreak\Service;

final class AuthLogic
{
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public static function sanitizeEmailHeader(string $email): string
    {
        return str_replace(["\r", "\n"], '', self::normalizeEmail($email));
    }

    public static function normalizeDisplayName(string $displayName): string
    {
        return mb_substr(trim($displayName), 0, 80);
    }

    public static function isPasswordValid(string $password): bool
    {
        return mb_strlen($password) >= 12;
    }

    public static function clampWindowDays(int $days): int
    {
        return max(1, min(30, $days));
    }

    public static function shouldThrottle(int $ipFailures, int $emailFailures, int $maxIpFailures = 10, int $maxEmailFailures = 5): bool
    {
        return $ipFailures >= $maxIpFailures || $emailFailures >= $maxEmailFailures;
    }

    public static function tokenHash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
