<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Csrf;
use Daybreak\Service\AuthService;
use Daybreak\Service\DedupService;
use Daybreak\Service\KiojuService;

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

        // Pagination params (both modes).
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = 20;

        $activeCategory = isset($args['slug']) && $args['slug'] !== '' ? $args['slug'] : null;

        // Categories for the filter bar.
        $categories = Database::query(
            'SELECT id, name, slug, color FROM source_categories ORDER BY sort_order'
        )->fetchAll();

        if ($activeCategory !== null) {
            $valid = false;
            foreach ($categories as $cat) {
                if ($cat['slug'] === $activeCategory) {
                    $valid = true;
                    break;
                }
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

        // Build language filter from user preference.
        $rawLangPref = $user['preferred_languages'] ?? null;
        $preferredLangs = (is_string($rawLangPref) && $rawLangPref !== '')
            ? (json_decode($rawLangPref, true) ?? [])
            : [];
        $langJoin = '';
        $langParams = [];
        if (is_array($preferredLangs) && $preferredLangs !== []) {
            $placeholders = implode(',', array_fill(0, count($preferredLangs), '?'));
            $langJoin = "AND (s.language IS NULL OR s.language IN ({$placeholders}))";
            $langParams = array_values($preferredLangs);
        }

        // Param order must match the SQL binding order:
        // 1. lang placeholders (in JOIN sources clause)
        // 2. $userId (in LEFT JOIN user_sources ON clause)
        // 3. date param (in WHERE)
        // 4. category param (in WHERE, optional)
        $params = $langParams;
        $params[] = $userId;

        if ($sinceQuery) {
            // Use fetched_at (when the article arrived in the DB), not published_at.
            // published_at can predate last_seen_at for sources like CISA KEV or backdated RSS items.
            $dateWhere = 'AND a.fetched_at > ?';
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

        $articles = DedupService::group(Database::query(
            "SELECT a.id, a.title, a.url, a.summary, a.published_at, a.dedup_key,
                    s.name AS source_name, s.attribution_text,
                    c.name AS category, c.slug AS cat_slug, c.color
             FROM articles a
             JOIN sources s ON s.id = a.source_id
                 AND s.status IN ('active', 'degraded')
                 AND s.adapter_type IN ('rss_atom', 'json_api')
                 {$langJoin}
             LEFT JOIN source_categories c ON c.id = s.category_id
             LEFT JOIN user_sources us ON us.source_id = s.id AND us.user_id = ?
             WHERE (us.enabled IS NULL OR us.enabled = 1)
             {$dateWhere}
             {$catWhere}
             ORDER BY a.published_at DESC
             LIMIT 2000",
            $params
        )->fetchAll());

        // Load watch terms and starred IDs before slicing (both need the full result set).
        $rawTerms   = Database::query(
            'SELECT id, term FROM user_watch_terms WHERE user_id = ? ORDER BY created_at ASC',
            [$userId]
        )->fetchAll();
        $lowerTerms = array_map(fn($t) => mb_strtolower($t['term']), $rawTerms);
        $watchTerms = array_column($rawTerms, 'term');

        $starredIds = array_flip(array_map('intval', Database::query(
            'SELECT article_id FROM user_starred_articles WHERE user_id = ?', [$userId]
        )->fetchAll(\PDO::FETCH_COLUMN)));

        $alertArticles = [];
        foreach ($articles as &$a) {
            $a['starred'] = isset($starredIds[(int) ($a['id'] ?? 0)]);
            if ($lowerTerms === []) {
                $a['watch_match'] = false;
                continue;
            }
            $hay = mb_strtolower($a['title'] . ' ' . ($a['summary'] ?? ''));
            $a['watch_match'] = false;
            foreach ($lowerTerms as $lt) {
                if (str_contains($hay, $lt)) {
                    $a['watch_match'] = true;
                    break;
                }
            }
            if ($a['watch_match']) {
                $alertArticles[] = $a;
            }
        }
        unset($a);

        // Compute total and unreadCount BEFORE slicing so the "N new items" banner
        // reflects the full deduplicated result, not just the current page.
        $total       = count($articles);
        $totalPages  = max(1, (int) ceil($total / $limit));
        $page        = min($page, $totalPages);
        $unreadCount = $sinceQuery ? $total : null;
        $articles    = array_slice($articles, ($page - 1) * $limit, $limit);

        $paginationBase = ($activeCategory !== null
            ? '/feed/category/' . rawurlencode($activeCategory) . '?days=' . $windowMode
            : '/feed?days=' . $windowMode
        ) . '&page=';

        // Widget rail (same as public page, not filtered by user sources).
        $ransomlookItems = Database::query(
            "SELECT a.title, a.url, a.published_at
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type = 'ransomlook'
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               ORDER BY a.published_at DESC LIMIT 10"
        )->fetchAll();

        $cveItems = Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type IN ('nvd','cisa_kev')
               ORDER BY a.published_at DESC LIMIT 10"
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
        $markSeenReturnTo = $activeCategory !== null
            ? '/feed/category/' . rawurlencode($activeCategory) . '?days=since'
            : '/feed?days=since';
        $activeNav = 'myfeed';
        $showWidgets = true;
        $canBookmarkToKioju = KiojuService::hasApiKey($userId);

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/feed/personalised.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function rss(array $args = []): void
    {
        $rawToken = (string) ($_GET['token'] ?? '');
        if ($rawToken === '') {
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Missing token.';
            return;
        }

        $tokenHash = hash('sha256', $rawToken);
        $user = Database::query(
            'SELECT u.* FROM user_feed_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ?',
            [$tokenHash]
        )->fetch();

        if (!$user) {
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Invalid token.';
            return;
        }

        $userId = (int) $user['id'];

        $rawLangPref    = $user['preferred_languages'] ?? null;
        $preferredLangs = (is_string($rawLangPref) && $rawLangPref !== '')
            ? (json_decode($rawLangPref, true) ?? []) : [];
        $langJoin   = '';
        $langParams = [];
        if (is_array($preferredLangs) && $preferredLangs !== []) {
            $placeholders = implode(',', array_fill(0, count($preferredLangs), '?'));
            $langJoin   = "AND (s.language IS NULL OR s.language IN ({$placeholders}))";
            $langParams = array_values($preferredLangs);
        }

        $params   = $langParams;
        $params[] = $userId;

        $articles = DedupService::group(Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at, a.dedup_key,
                    s.name AS source_name, s.attribution_text,
                    c.name AS category, c.color
             FROM articles a
             JOIN sources s ON s.id = a.source_id
                 AND s.status IN ('active', 'degraded')
                 AND s.adapter_type IN ('rss_atom', 'json_api')
                 {$langJoin}
             LEFT JOIN source_categories c ON c.id = s.category_id
             LEFT JOIN user_sources us ON us.source_id = s.id AND us.user_id = ?
             WHERE (us.enabled IS NULL OR us.enabled = 1)
               AND a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY a.published_at DESC
             LIMIT 50",
            $params
        )->fetchAll());

        $appUrl  = rtrim((string) (\Daybreak\Config::get('APP_URL', '') ?? ''), '/');
        $feedUrl = $appUrl . '/feed/rss?token=' . rawurlencode($rawToken);

        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: private, max-age=300');
        header('X-Robots-Tag: noindex');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo '<channel>' . "\n";
        echo '  <title>Daybreak Security Feed</title>' . "\n";
        echo '  <link>' . htmlspecialchars($appUrl . '/feed', ENT_XML1) . '</link>' . "\n";
        echo '  <description>Personalised security news from Daybreak</description>' . "\n";
        echo '  <language>en</language>' . "\n";
        echo '  <ttl>5</ttl>' . "\n";
        echo '  <atom:link href="' . htmlspecialchars($feedUrl, ENT_XML1) . '" rel="self" type="application/rss+xml"/>' . "\n";
        echo '  <lastBuildDate>' . date(DATE_RSS) . '</lastBuildDate>' . "\n";

        foreach ($articles as $a) {
            $pubDate = !empty($a['published_at'])
                ? date(DATE_RSS, strtotime($a['published_at']))
                : date(DATE_RSS);
            $guid = 'daybreak:' . ($a['dedup_key'] ?? hash('sha256', $a['url']));
            $desc = '';
            if (!empty($a['summary'])) {
                $desc .= \Daybreak\Security\Html::sanitizeSummary((string) $a['summary'], 500);
            }
            if (!empty($a['attribution_text'])) {
                $desc .= ($desc !== '' ? '<br><br>' : '') . htmlspecialchars($a['attribution_text'], ENT_XML1);
            }

            echo '  <item>' . "\n";
            echo '    <title><![CDATA[' . $a['title'] . ']]></title>' . "\n";
            echo '    <link>' . htmlspecialchars($a['url'], ENT_XML1) . '</link>' . "\n";
            echo '    <guid isPermaLink="false">' . htmlspecialchars($guid, ENT_XML1) . '</guid>' . "\n";
            echo '    <pubDate>' . htmlspecialchars($pubDate, ENT_XML1) . '</pubDate>' . "\n";
            if ($desc !== '') {
                echo '    <description><![CDATA[' . $desc . ']]></description>' . "\n";
            }
            echo '  </item>' . "\n";
        }

        echo '</channel>' . "\n";
        echo '</rss>' . "\n";
    }

    public function markSeen(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $user = AuthService::currentUser();
        AuthService::updateLastSeen((int) $user['id']);

        $_SESSION['flash'] = 'Feed marked as seen.';
        $returnTo = $this->safeReturnPath((string) ($_POST['return_to'] ?? '/feed?days=since'));
        header('Location: ' . $returnTo);
        exit;
    }

    private function safeReturnPath(string $candidate): string
    {
        $default = '/feed?days=since';
        $candidate = trim($candidate);
        if ($candidate === '') {
            return $default;
        }

        $parts = parse_url($candidate);
        if (!is_array($parts)) {
            return $default;
        }

        // Reject anything that is not a plain local path/query.
        if (
            isset($parts['scheme']) || isset($parts['host']) || isset($parts['user'])
            || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])
        ) {
            return $default;
        }

        $path = (string) ($parts['path'] ?? '');
        $isFeedPath = $path === '/feed' || preg_match('#^/feed/category/[a-z0-9-]+$#', $path) === 1;
        if (!$isFeedPath) {
            return $default;
        }

        $days = 'since';
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $queryParams);
            $requestedDays = (string) ($queryParams['days'] ?? 'since');
            if (in_array($requestedDays, ['since', '1', '3', '7', '30'], true)) {
                $days = $requestedDays;
            }
        }

        return $path . '?days=' . rawurlencode($days);
    }
}
