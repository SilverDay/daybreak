<?php

declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Database;

/**
 * Server-side integration for kioju.de link storage API.
 */
final class KiojuService
{
    private const API_URL = 'https://kioju.de/api/api.php';
    private static ?bool $schemaReady = null;

    public static function hasApiKey(int $userId): bool
    {
        if (!self::schemaReady()) {
            return false;
        }

        $row = Database::query('SELECT kioju_api_key_enc FROM users WHERE id = ?', [$userId])->fetch();
        return isset($row['kioju_api_key_enc']) && (string) $row['kioju_api_key_enc'] !== '';
    }

    public static function setApiKey(int $userId, string $apiKey): bool
    {
        if (!self::schemaReady()) {
            return false;
        }

        $apiKey = trim($apiKey);
        if ($apiKey === '' || strlen($apiKey) > 512) {
            return false;
        }

        $encrypted = CredentialVault::encrypt($apiKey);
        Database::query(
            'UPDATE users SET kioju_api_key_enc = ?, kioju_connected_at = NOW() WHERE id = ?',
            [$encrypted, $userId]
        );

        AuditLog::write('kioju.key.updated', 'user', (string) $userId);
        return true;
    }

    public static function clearApiKey(int $userId): void
    {
        if (!self::schemaReady()) {
            return;
        }

        Database::query(
            'UPDATE users SET kioju_api_key_enc = NULL, kioju_connected_at = NULL WHERE id = ?',
            [$userId]
        );

        AuditLog::write('kioju.key.removed', 'user', (string) $userId);
    }

    /**
     * @return array{ok:bool,message:string,status:int}
     */
    public static function addBookmark(int $userId, string $url, string $title = ''): array
    {
        $apiKey = self::apiKeyForUser($userId);
        if ($apiKey === null) {
            return ['ok' => false, 'message' => 'No Kioju API key configured.', 'status' => 0];
        }

        $fetcher = new FeedFetcher();
        $response = $fetcher->postForm(
            self::API_URL,
            [
                'action' => 'add',
                'url' => $url,
                'title' => $title,
                'is_private' => '1',
                'capture_description' => '1',
            ],
            ['X-Api-Key: ' . $apiKey]
        );

        $status = (int) $response['status'];
        $payload = json_decode($response['body'], true);
        $message = is_array($payload) && isset($payload['message']) ? (string) $payload['message'] : 'Bookmark sync failed.';

        if ($status === 201) {
            AuditLog::write('kioju.bookmark.added', 'user', (string) $userId);
            return ['ok' => true, 'message' => 'Saved to Kioju.', 'status' => 201];
        }

        if ($status === 409) {
            return ['ok' => true, 'message' => 'Already in your Kioju bookmarks.', 'status' => 409];
        }

        if ($status === 401 || $status === 403) {
            return ['ok' => false, 'message' => 'Kioju API key rejected. Please update it in account settings.', 'status' => $status];
        }

        if ($status === 429) {
            return ['ok' => false, 'message' => $message !== '' ? $message : 'Kioju rate limit reached. Please try again later.', 'status' => 429];
        }

        if ($status >= 500) {
            return ['ok' => false, 'message' => 'Kioju is temporarily unavailable. Please try again later.', 'status' => $status];
        }

        return ['ok' => false, 'message' => $message, 'status' => $status];
    }

    private static function apiKeyForUser(int $userId): ?string
    {
        if (!self::schemaReady()) {
            return null;
        }

        $row = Database::query('SELECT kioju_api_key_enc FROM users WHERE id = ?', [$userId])->fetch();
        $encrypted = (string) ($row['kioju_api_key_enc'] ?? '');
        if ($encrypted === '') {
            return null;
        }

        return CredentialVault::decrypt($encrypted);
    }

    private static function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        try {
            $hasEnc = Database::query("SHOW COLUMNS FROM users LIKE 'kioju_api_key_enc'")->fetch() !== false;
            $hasConnected = Database::query("SHOW COLUMNS FROM users LIKE 'kioju_connected_at'")->fetch() !== false;
            self::$schemaReady = $hasEnc && $hasConnected;
        } catch (\Throwable) {
            self::$schemaReady = false;
        }

        return self::$schemaReady;
    }
}
