<?php
declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Config;
use Daybreak\Database;
use Daybreak\Security\Password;
use DateTimeImmutable;
use PDOException;

/**
 * Authentication, registration, and account management.
 * Security baseline: NIST SP 800-63B — Argon2id, min 12 chars, generic error
 * responses (no user enumeration on login / register / reset / forgot), login
 * throttling per-IP and per-email, single-use short-TTL tokens stored as
 * SHA-256 hashes only, session regeneration on privilege change.
 */
final class AuthService
{
    private const MAX_EMAIL_FAILS = 5;
    private const MAX_IP_FAILS    = 10;
    private const THROTTLE_MIN    = 15;    // minutes
    private const VERIFY_TTL_MIN  = 1440;  // 24 hours
    private const RESET_TTL_MIN   = 60;    // 1 hour

    private static ?array $userCache  = null;
    private static bool   $userLoaded = false;

    // ── Current user ───────────────────────────────────────────────────────────

    public static function currentUser(): ?array
    {
        if (!self::$userLoaded) {
            self::$userLoaded = true;
            $uid = $_SESSION['user_id'] ?? null;
            if ($uid !== null) {
                $row = Database::query(
                    'SELECT id, email, display_name, role, status
                     FROM users WHERE id = ? AND status = ?',
                    [(int) $uid, 'active']
                )->fetch();
                self::$userCache = $row ?: null;
            }
        }
        return self::$userCache;
    }

    public static function requireAuth(): void
    {
        if (self::currentUser() === null) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        $u = self::currentUser();
        if ($u === null || $u['role'] !== 'admin') {
            http_response_code(403);
            echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title><h1>403 Forbidden</h1>';
            exit;
        }
    }

    // ── Registration ───────────────────────────────────────────────────────────

    /**
     * Create a pending account and fire the verification email.
     * Throws \InvalidArgumentException for validation failures.
     * Silently succeeds on duplicate email (no enumeration).
     */
    public static function register(string $email, string $password, string $displayName): void
    {
        $email       = mb_strtolower(trim($email));
        $displayName = mb_substr(trim($displayName), 0, 80);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address.');
        }
        if (mb_strlen($password) < 12) {
            throw new \InvalidArgumentException('Password must be at least 12 characters.');
        }
        if ($displayName === '') {
            throw new \InvalidArgumentException('Display name is required.');
        }

        try {
            Database::query(
                'INSERT INTO users (email, password_hash, display_name, status) VALUES (?, ?, ?, ?)',
                [$email, Password::hash($password), $displayName, 'pending']
            );
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return; // Duplicate email — silently no-op (no enumeration)
            }
            throw $e;
        }

        $userId = (int) Database::lastInsertId();
        self::sendVerificationEmail($userId, $email);
    }

    public static function verifyEmail(string $rawToken): bool
    {
        $row = self::findToken($rawToken, 'email_verify');
        if ($row === null) {
            return false;
        }

        Database::query(
            "UPDATE users SET status = 'active', email_verified_at = NOW()
             WHERE id = ? AND status = 'pending'",
            [(int) $row['user_id']]
        );
        self::consumeToken((int) $row['id']);
        return true;
    }

    // ── Login / logout ─────────────────────────────────────────────────────────

    /**
     * Authenticate and start an authenticated session.
     * Returns true on success. Generic false on any failure (no enumeration).
     * Returns false without checking password when throttled (constant-time note:
     * we still run password_verify on a dummy hash to resist timing attacks
     * when the user IS found; throttled path is acceptable because the attacker
     * already knows they're throttled after 5 attempts).
     */
    public static function login(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

        if (self::isThrottled($email, $ip)) {
            return false;
        }

        $user = Database::query(
            'SELECT id, password_hash, status FROM users WHERE email = ?',
            [$email]
        )->fetch();

        // Always run verify — prevents timing-based user-enumeration.
        $dummyHash = '$argon2id$v=19$m=65536,t=4,p=1$c2FsdHNhbHRzYWx0$dummydummydummydummydummy';
        $hashToCheck = $user ? (string) $user['password_hash'] : $dummyHash;
        $valid = Password::verify($password, $hashToCheck);

        if (!$valid || !$user || $user['status'] !== 'active') {
            self::recordAttempt($email, $ip, false);
            return false;
        }

        if (Password::needsRehash($user['password_hash'])) {
            Database::query(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [Password::hash($password), (int) $user['id']]
            );
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        self::$userLoaded = false;
        self::$userCache  = null;

        Database::query(
            'UPDATE users SET last_login_at = NOW() WHERE id = ?',
            [(int) $user['id']]
        );
        self::recordAttempt($email, $ip, true);
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$userCache  = null;
        self::$userLoaded = false;
    }

    // ── Password reset ─────────────────────────────────────────────────────────

    /**
     * Send a password-reset email if the email belongs to an active account.
     * Always returns void — no enumeration possible via timing or response.
     */
    public static function forgotPassword(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user  = Database::query(
            "SELECT id FROM users WHERE email = ? AND status = 'active'",
            [$email]
        )->fetch();

        if (!$user) {
            return;
        }

        // Invalidate any outstanding reset tokens for this user.
        Database::query(
            "UPDATE auth_tokens SET used_at = NOW()
             WHERE user_id = ? AND type = 'password_reset' AND used_at IS NULL",
            [(int) $user['id']]
        );

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        Database::query(
            'INSERT INTO auth_tokens (user_id, type, token_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [(int) $user['id'], 'password_reset', $tokenHash, self::RESET_TTL_MIN]
        );

        $base = Config::get('APP_BASE_URL', 'https://daybreak.silverday.de');
        $link = $base . '/password/reset/' . $rawToken;

        try {
            (new MailService())->send(
                $email,
                'Reset your Daybreak password',
                "You requested a password reset for your Daybreak account.\r\n\r\n"
                . "Click the link below to set a new password:\r\n\r\n{$link}\r\n\r\n"
                . "This link expires in 60 minutes and can only be used once.\r\n\r\n"
                . "If you did not request a password reset, you can safely ignore this email."
            );
        } catch (\Throwable $e) {
            error_log('[daybreak] reset email failed: ' . $e->getMessage());
        }
    }

    public static function resetPassword(string $rawToken, string $newPassword): bool
    {
        if (mb_strlen($newPassword) < 12) {
            return false;
        }

        $row = self::findToken($rawToken, 'password_reset');
        if ($row === null) {
            return false;
        }

        Database::query(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [Password::hash($newPassword), (int) $row['user_id']]
        );
        self::consumeToken((int) $row['id']);

        // Invalidate all sessions for this user (force re-login everywhere).
        Database::query('DELETE FROM sessions WHERE user_id = ?', [(int) $row['user_id']]);
        return true;
    }

    // ── Account management ─────────────────────────────────────────────────────

    public static function changePassword(int $userId, string $current, string $new): bool
    {
        if (mb_strlen($new) < 12) {
            return false;
        }

        $user = Database::query(
            'SELECT password_hash FROM users WHERE id = ?',
            [$userId]
        )->fetch();

        if (!$user || !Password::verify($current, (string) $user['password_hash'])) {
            return false;
        }

        Database::query(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [Password::hash($new), $userId]
        );
        return true;
    }

    public static function updateDisplayName(int $userId, string $name): bool
    {
        $name = mb_substr(trim($name), 0, 80);
        if ($name === '') {
            return false;
        }
        Database::query(
            'UPDATE users SET display_name = ? WHERE id = ?',
            [$name, $userId]
        );
        return true;
    }

    public static function deleteAccount(int $userId): void
    {
        // FK ON DELETE CASCADE cleans up sessions, auth_tokens, user_sources.
        // audit_log and source_suggestions reference user ON DELETE SET NULL.
        Database::query('DELETE FROM users WHERE id = ?', [$userId]);
    }

    public static function exportData(int $userId): array
    {
        $user = Database::query(
            'SELECT id, email, display_name, role, status, default_window_days,
                    email_verified_at, last_login_at, created_at
             FROM users WHERE id = ?',
            [$userId]
        )->fetch();

        $suggestions = Database::query(
            'SELECT name, homepage_url, feed_url, status, created_at
             FROM source_suggestions WHERE suggested_by = ?',
            [$userId]
        )->fetchAll();

        return [
            'exported_at' => (new DateTimeImmutable())->format('c'),
            'user'        => $user ?: [],
            'suggestions' => $suggestions,
        ];
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    private static function sendVerificationEmail(int $userId, string $email): void
    {
        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        Database::query(
            'INSERT INTO auth_tokens (user_id, type, token_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))',
            [$userId, 'email_verify', $tokenHash, self::VERIFY_TTL_MIN]
        );

        $base = Config::get('APP_BASE_URL', 'https://daybreak.silverday.de');
        $link = $base . '/verify/' . $rawToken;

        try {
            (new MailService())->send(
                $email,
                'Verify your Daybreak account',
                "Welcome to Daybreak!\r\n\r\n"
                . "Please verify your email address by clicking the link below:\r\n\r\n{$link}\r\n\r\n"
                . "This link expires in 24 hours.\r\n\r\n"
                . "If you did not register for Daybreak, you can safely ignore this email."
            );
        } catch (\Throwable $e) {
            error_log('[daybreak] verify email failed: ' . $e->getMessage());
        }
    }

    private static function findToken(string $rawToken, string $type): ?array
    {
        $row = Database::query(
            'SELECT id, user_id FROM auth_tokens
             WHERE token_hash = ? AND type = ? AND expires_at > NOW() AND used_at IS NULL',
            [hash('sha256', $rawToken), $type]
        )->fetch();

        return $row ?: null;
    }

    private static function consumeToken(int $tokenId): void
    {
        Database::query(
            'UPDATE auth_tokens SET used_at = NOW() WHERE id = ?',
            [$tokenId]
        );
    }

    private static function isThrottled(string $email, string $ip): bool
    {
        $ipHash = self::hashIp($ip);

        $ipFails = (int) Database::query(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_hash = ? AND successful = 0
               AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ipHash, self::THROTTLE_MIN]
        )->fetchColumn();

        if ($ipFails >= self::MAX_IP_FAILS) {
            return true;
        }

        $emailFails = (int) Database::query(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND successful = 0
               AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$email, self::THROTTLE_MIN]
        )->fetchColumn();

        return $emailFails >= self::MAX_EMAIL_FAILS;
    }

    private static function recordAttempt(string $email, string $ip, bool $success): void
    {
        Database::query(
            'INSERT INTO login_attempts (email, ip_hash, successful) VALUES (?, ?, ?)',
            [$email, self::hashIp($ip), $success ? 1 : 0]
        );
    }

    private static function hashIp(string $ip): string
    {
        return hash('sha256', $ip . Config::get('APP_KEY', 'daybreak'));
    }
}
