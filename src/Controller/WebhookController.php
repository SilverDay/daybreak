<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Csrf;
use Daybreak\Security\Html;
use Daybreak\Security\SsrfGuard;
use Daybreak\Service\AuditLog;
use Daybreak\Service\AuthService;

/** User-facing webhook management: list, create, toggle, delete. */
final class WebhookController
{
    private const MAX_WEBHOOKS = 10;
    private const ALLOWED_FORMATS = ['slack', 'discord', 'teams', 'generic'];

    public function showWebhooks(array $args = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::currentUser()['id'];

        $webhooks = Database::query(
            'SELECT id, name, url, format, filter_json, active, created_at
             FROM user_webhooks WHERE user_id = ? ORDER BY created_at ASC',
            [$userId]
        )->fetchAll();

        $categories = Database::query(
            'SELECT slug, name FROM source_categories ORDER BY sort_order'
        )->fetchAll();

        $activeSources = Database::query(
            "SELECT slug, name FROM sources WHERE status IN ('active','degraded') ORDER BY name"
        )->fetchAll();

        $recentLog = Database::query(
            'SELECT wl.webhook_id, wl.article_title, wl.status, wl.http_status, wl.created_at
             FROM webhook_log wl
             WHERE wl.webhook_id IN (
                 SELECT id FROM user_webhooks WHERE user_id = ?
             )
             ORDER BY wl.created_at DESC LIMIT 20',
            [$userId]
        )->fetchAll();

        $title         = 'Webhooks';
        $settingsNav   = 'webhooks';

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/settings_layout.php';
        include DB_ROOT . '/src/View/user/webhooks.php';
        include DB_ROOT . '/src/View/settings_layout_end.php';
    }

    public function handleCreate(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $userId = (int) AuthService::currentUser()['id'];

        // Enforce per-user cap.
        $count = (int) Database::query(
            'SELECT COUNT(*) FROM user_webhooks WHERE user_id = ?', [$userId]
        )->fetchColumn();
        if ($count >= self::MAX_WEBHOOKS) {
            $_SESSION['flash_error'] = 'Maximum of ' . self::MAX_WEBHOOKS . ' webhooks reached.';
            header('Location: /settings/webhooks');
            exit;
        }

        $name   = trim((string) ($_POST['name']   ?? ''));
        $url    = trim((string) ($_POST['url']     ?? ''));
        $format = trim((string) ($_POST['format']  ?? 'generic'));

        if ($name === '' || mb_strlen($name) > 120) {
            $_SESSION['flash_error'] = 'Name must be 1–120 characters.';
            header('Location: /settings/webhooks');
            exit;
        }

        if (!in_array($format, self::ALLOWED_FORMATS, true)) {
            $_SESSION['flash_error'] = 'Invalid format.';
            header('Location: /settings/webhooks');
            exit;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $_SESSION['flash_error'] = 'Webhook URL must start with http:// or https://.';
            header('Location: /settings/webhooks');
            exit;
        }

        try {
            SsrfGuard::assertSafe($url);
        } catch (\RuntimeException) {
            $_SESSION['flash_error'] = 'That URL is not allowed (SSRF guard).';
            header('Location: /settings/webhooks');
            exit;
        }

        $recentMutations = (int) Database::query(
            "SELECT COUNT(*) FROM audit_log
             WHERE user_id = ? AND action IN ('webhook.create','webhook.update')
               AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$userId]
        )->fetchColumn();
        if ($recentMutations >= 5) {
            $_SESSION['flash_error'] = 'Too many requests. Please wait a moment.';
            header('Location: /settings/webhooks');
            exit;
        }

        $filterJson = $this->buildFilterJson($userId);

        Database::query(
            'INSERT INTO user_webhooks (user_id, name, url, format, filter_json)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $name, $url, $format, $filterJson]
        );
        AuditLog::write('webhook.create', 'webhook', (string) Database::lastInsertId());

        $_SESSION['flash'] = 'Webhook added.';
        header('Location: /settings/webhooks');
        exit;
    }

    public function handleUpdate(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $userId    = (int) AuthService::currentUser()['id'];
        $webhookId = (int) ($args['id'] ?? 0);
        $action    = trim((string) ($_POST['action'] ?? ''));

        if ($webhookId <= 0) {
            header('Location: /settings/webhooks');
            exit;
        }

        if ($action === 'delete') {
            // WHERE user_id = ? prevents cross-user deletion.
            Database::query(
                'DELETE FROM user_webhooks WHERE id = ? AND user_id = ?',
                [$webhookId, $userId]
            );
            AuditLog::write('webhook.delete', 'webhook', (string) $webhookId);
            $_SESSION['flash'] = 'Webhook deleted.';
        } elseif ($action === 'toggle') {
            Database::query(
                'UPDATE user_webhooks SET active = 1 - active WHERE id = ? AND user_id = ?',
                [$webhookId, $userId]
            );
            AuditLog::write('webhook.update', 'webhook', (string) $webhookId);
            $_SESSION['flash'] = 'Webhook updated.';
        }

        header('Location: /settings/webhooks');
        exit;
    }

    /**
     * Build a filter_json string from POST data, or null if no filters set.
     * Validates terms (max 20, each ≤ 80 chars, stripped of HTML),
     * category slugs, and source slugs (both checked against real DB values).
     */
    private function buildFilterJson(int $userId): ?string
    {
        $rawTerms = trim((string) ($_POST['filter_terms'] ?? ''));
        $terms    = [];
        if ($rawTerms !== '') {
            foreach (explode(',', $rawTerms) as $t) {
                $t = trim(strip_tags($t));
                if ($t !== '' && mb_strlen($t) <= 80) {
                    $terms[] = $t;
                }
            }
            $terms = array_values(array_unique(array_slice($terms, 0, 20)));
        }

        $submittedCats = (array) ($_POST['filter_categories'] ?? []);
        $validCatSlugs = Database::query(
            'SELECT slug FROM source_categories'
        )->fetchAll(\PDO::FETCH_COLUMN);
        $validCatSet   = array_flip($validCatSlugs);
        $cats          = array_values(array_filter(
            array_map('strval', $submittedCats),
            static fn($s) => isset($validCatSet[$s])
        ));

        $submittedSrcs  = (array) ($_POST['filter_sources'] ?? []);
        $validSrcSlugs  = Database::query(
            "SELECT slug FROM sources WHERE status IN ('active','degraded')"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $validSrcSet    = array_flip($validSrcSlugs);
        $sources        = array_values(array_filter(
            array_map('strval', $submittedSrcs),
            static fn($s) => isset($validSrcSet[$s])
        ));

        if ($terms === [] && $cats === [] && $sources === []) {
            return null;
        }

        return json_encode(
            array_filter(['terms' => $terms, 'categories' => $cats, 'sources' => $sources]),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
