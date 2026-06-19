<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Csrf;
use Daybreak\Security\Html;
use Daybreak\Service\AuthService;
use Daybreak\Service\KiojuService;

/** Account settings, DSGVO data export, and account deletion. */
final class UserController
{
    public function showAccount(array $args = []): void
    {
        AuthService::requireAuth();
        $user        = AuthService::currentUser();
        $userId      = (int) ($user['id'] ?? 0);
        $hasKiojuKey = KiojuService::hasApiKey($userId);
        $title       = 'General';
        $settingsNav = 'general';

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/settings_layout.php';
        include DB_ROOT . '/src/View/user/general.php';
        include DB_ROOT . '/src/View/settings_layout_end.php';
    }

    public function showSecurity(array $args = []): void
    {
        AuthService::requireAuth();
        $title       = 'Security';
        $settingsNav = 'security';

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/settings_layout.php';
        include DB_ROOT . '/src/View/user/security.php';
        include DB_ROOT . '/src/View/settings_layout_end.php';
    }

    public function handleSecurity(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user   = AuthService::currentUser();
        $userId = (int) $user['id'];

        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) {
            $_SESSION['flash_error'] = 'New passwords do not match.';
        } elseif (mb_strlen($new) < 12) {
            $_SESSION['flash_error'] = 'Password must be at least 12 characters.';
        } elseif (!AuthService::changePassword($userId, $current, $new)) {
            $_SESSION['flash_error'] = 'Current password is incorrect.';
        } else {
            $_SESSION['flash'] = 'Password updated.';
        }

        header('Location: /settings/security');
        exit;
    }

    public function handleAccount(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user   = AuthService::currentUser();
        $userId = (int) $user['id'];
        $action = $_POST['action'] ?? '';

        if ($action === 'window') {
            $days = max(1, min(30, (int) ($_POST['default_window_days'] ?? 1)));
            AuthService::updateWindowDays($userId, $days);
            $_SESSION['flash'] = 'Default window updated.';
        } elseif ($action === 'name') {
            $name = trim($_POST['display_name'] ?? '');
            if (!AuthService::updateDisplayName($userId, $name)) {
                $_SESSION['flash_error'] = 'Display name cannot be empty.';
            } else {
                $_SESSION['flash'] = 'Display name updated.';
            }
        } elseif ($action === 'kioju_save') {
            $apiKey = trim((string) ($_POST['kioju_api_key'] ?? ''));
            if (!KiojuService::setApiKey($userId, $apiKey)) {
                $_SESSION['flash_error'] = 'Please provide a valid Kioju API key.';
            } else {
                $_SESSION['flash'] = 'Kioju API key saved.';
            }
        } elseif ($action === 'kioju_remove') {
            KiojuService::clearApiKey($userId);
            $_SESSION['flash'] = 'Kioju API key removed.';
        }

        header('Location: /settings/account');
        exit;
    }

    public function deleteAccount(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user     = AuthService::currentUser();
        $userId   = (int) $user['id'];
        $confirm  = trim($_POST['confirm'] ?? '');

        if ($confirm !== 'DELETE') {
            $_SESSION['flash_error'] = 'Type DELETE to confirm account deletion.';
            header('Location: /settings/account');
            exit;
        }

        AuthService::logout();
        AuthService::deleteAccount($userId);

        // Restart a fresh anonymous session.
        session_start();
        $_SESSION['flash'] = 'Your account has been permanently deleted.';
        header('Location: /');
        exit;
    }

    public function showSources(array $args = []): void
    {
        AuthService::requireAuth();
        $user   = AuthService::currentUser();
        $userId = (int) $user['id'];

        // All active feed sources (widgets excluded — they're not user-selectable).
        $sources = Database::query(
            "SELECT s.id, s.name, s.attribution_text, s.language,
                    c.name AS category_name, c.slug AS category_slug, c.sort_order
             FROM sources s
             LEFT JOIN source_categories c ON c.id = s.category_id
             WHERE s.status IN ('active', 'degraded')
               AND s.adapter_type IN ('rss_atom', 'json_api')
             ORDER BY c.sort_order, s.name"
        )->fetchAll();

        // Disabled source IDs for this user (absent row = opted-in by default).
        $disabledRaw = Database::query(
            'SELECT source_id FROM user_sources WHERE user_id = ? AND enabled = 0',
            [$userId]
        )->fetchAll(\PDO::FETCH_COLUMN);
        $disabledIds = array_flip(array_map('intval', $disabledRaw));

        // Available languages from active sources that have one set.
        $availableLanguages = Database::query(
            "SELECT DISTINCT language FROM sources
             WHERE language IS NOT NULL
               AND status IN ('active', 'degraded')
               AND adapter_type IN ('rss_atom', 'json_api')
             ORDER BY language"
        )->fetchAll(\PDO::FETCH_COLUMN);

        // User's current language preference.
        $rawLangPref = $user['preferred_languages'] ?? null;
        $preferredLanguages = (is_string($rawLangPref) && $rawLangPref !== '')
            ? (json_decode($rawLangPref, true) ?? [])
            : [];

        // Filter sources by language when a preference is set.
        // Sources without a language tag are always included.
        if ($preferredLanguages !== []) {
            $prefSet = array_flip($preferredLanguages);
            $sources = array_values(array_filter(
                $sources,
                static fn($s) => $s['language'] === null || isset($prefSet[$s['language']])
            ));
        }

        // Group sources by category name.
        $grouped = [];
        foreach ($sources as $s) {
            $grouped[$s['category_name'] ?? 'Uncategorized'][] = $s;
        }

        $title       = 'Edit feed';
        $settingsNav = 'feed';

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/settings_layout.php';
        include DB_ROOT . '/src/View/user/sources.php';
        include DB_ROOT . '/src/View/settings_layout_end.php';
    }

    public function handleSources(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user   = AuthService::currentUser();
        $userId = (int) $user['id'];

        $action = $_POST['action'] ?? 'sources';

        if ($action === 'languages') {
            $allowedLanguages = ['en','de','fr','es','pt','nl','it','ja','zh','ko','ru','ar','pl','sv','fi','da','no'];
            $submittedLangs = array_values(array_filter(
                array_unique((array) ($_POST['preferred_languages'] ?? [])),
                static fn($c) => in_array($c, $allowedLanguages, true)
            ));
            $langJson = $submittedLangs !== [] ? json_encode($submittedLangs, JSON_THROW_ON_ERROR) : null;
            Database::query(
                'UPDATE users SET preferred_languages = ? WHERE id = ?',
                [$langJson, $userId]
            );
            $_SESSION['flash'] = 'Language filter saved.';
        } else {
            // Authoritative list of selectable source IDs from the DB.
            $allIds = array_map('intval', Database::query(
                "SELECT id FROM sources
                 WHERE status IN ('active', 'degraded') AND adapter_type IN ('rss_atom', 'json_api')"
            )->fetchAll(\PDO::FETCH_COLUMN));

            // IDs the user checked (submitted via checkboxes).
            $checkedIds = array_flip(array_map('intval', (array) ($_POST['sources'] ?? [])));

            // Clear existing preferences and insert disabled rows only.
            // Absent row = opted-in, so we only need to record opt-outs.
            Database::query('DELETE FROM user_sources WHERE user_id = ?', [$userId]);

            foreach ($allIds as $sid) {
                if (!isset($checkedIds[$sid])) {
                    Database::query(
                        'INSERT INTO user_sources (user_id, source_id, enabled) VALUES (?, ?, 0)',
                        [$userId, $sid]
                    );
                }
            }
            $_SESSION['flash'] = 'Source preferences saved.';
        }

        header('Location: /settings/sources');
        exit;
    }

    public function export(array $args = []): void
    {
        AuthService::requireAuth();

        $user = AuthService::currentUser();
        $data = AuthService::exportData((int) $user['id']);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="daybreak-data-export.json"');
        header('Content-Length: ' . strlen($json));
        echo $json;
    }
}
