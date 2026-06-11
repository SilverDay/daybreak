<?php
declare(strict_types=1);

namespace Daybreak\Security;

/** Argon2id hashing (NIST SP 800-63B baseline). Min length enforced at validation time (>=12). */
final class Password
{
    public static function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
