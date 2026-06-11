<?php
declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Html;
use Daybreak\Service\AuthService;

/**
 * Personalised feed for authenticated users.
 * Two modes: "since last visit" (default) and "last X days" (explicit ?days=N).
 * Only shows sources the user has not opted out of (user_sources.enabled = 0 = excluded;
 * absent row = included by default).
 */
final class FeedController
{
    public function feed(array $args = []): void
    {
        AuthService::requireAuth();
        $user   = AuthService::currentUser();
        $userId = (int) $user['id'];

        // Determine mode from ?days= param.
        // No param or ?days=since → since-last-visit mode.
        // ?days=N (integer) → last N days mode.
        $rawDays    = $_GET['days'] ?? 'since';
        $sinceMode  = ($rawDays === 'since');
        $windowDays = $sinceMode
            ? (int) ($user['default_window_days'] ?? 1)
            : max(1, min(30, (int) $rawDays));
        // $windowMode is consumed by layout.php for the window-select dropdown.
        $windowMode = $sinceMode ? 'since' : $windowDays;

        $activeCategory = isset($args['slug']) && $args['slug'] !== '' ? $args['slug'] : null;

        // Categories for the filter bar.
        $categories = Database::query(
            'SELECT id, name, slug, color FROM source_categories ORDER BY sort_order'
        )->fetchAll();

        if ($activeCategory !== null) {
            $valid = false;
            foreach ($categories as $cat) {
                if ($cat['slug'] === $activeCategory) { $valid = true; break; }
            }
            if (!$valid) {
                http_response_code(404);
                echo '<!doctype html><meta charset="utf-8"><title>Not Found · Daybreak</title><h1>Category not found</h1>';
                return;
            }
        }

        // Capture the current last_seen_at BEFORE updating it.
        $lastSeen   = $user['last_seen_at'] ?? null;
        // sinceQuery = true when we actually filter by the previous-visit timestamp.
        $sinceQuery = $sinceMode && ($lastSeen !== null);

        // Build query params: user_id first (consumed by LEFT JOIN ON clause).
        $params = [$userId];

        if ($sinceQuery) {
            $dateWhere = 'AND a.published_at > ?';
            $params[]  = $lastSeen;
        } else {
            $dateWhere = 'AND a.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
            $params[]  = $windowDays;
        }

        $catWhere = '';
        if ($activeCategory !== null) {
            $catWhere = 'AND c.slug = ?';
            $params[] = $activeCategory;
        }

        $articles = Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at,
                    s.name AS source_name, s.attribution_text,
                    c.name AS category, c.slug AS cat_slug, c.color
             FROM articles a
             JOIN sources s ON s.id = a.source_id
                 AND s.status IN ('active', 'degraded')
                 AND s.adapter_type IN ('rss_atom', 'json_api')
             LEFT JOIN source_categories c ON c.id = s.category_id
             LEFT JOIN user_sources us ON us.source_id = s.id AND us.user_id = ?
             WHERE (us.enabled IS NULL OR us.enabled = 1)
             {$dateWhere}
             {$catWhere}
             ORDER BY a.published_at DESC
             LIMIT 100",
            $params
        )->fetchAll();

        $unreadCount = $sinceQuery ? count($articles) : null;

        // Advance last_seen_at now that the user has seen the since-mode page.
        // Also initialise it on first visit.
        if ($sinceMode) {
            AuthService::updateLastSeen($userId);
        }

        // Widget rail (same as public page, not filtered by user sources).
        $ransomlookItems = Database::query(
            "SELECT a.title, a.url, a.published_at
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type = 'ransomlook'
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY a.published_at DESC LIMIT 20"
        )->fetchAll();

        $cveItems = Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type = 'nvd'
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY a.published_at DESC LIMIT 15"
        )->fetchAll();

        // Feed base paths for layout.php filter bar.
        $allFeedUrl    = '/feed';
        $catFeedBase   = '/feed/category/';
        $windowOptions = [
            'since' => 'Since last visit',
            1       => 'Last 24h',
            3       => 'Last 3 days',
            7       => 'Last 7 days',
            30      => 'Last 30 days',
        ];

        if ($activeCategory !== null) {
            $activeCatRow = array_values(array_filter($categories, fn($c) => $c['slug'] === $activeCategory));
            $title = ($activeCatRow[0]['name'] ?? 'Category') . ' · My Feed';
        } else {
            $title = 'My Feed';
        }
        $activeNav = 'myfeed';

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/feed/personalised.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }
}
