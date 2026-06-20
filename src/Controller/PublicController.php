<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Service\AuthService;
use Daybreak\Service\DedupService;
use Daybreak\Service\KiojuService;

/** Public news page: main feed + ransomlook/CVE widgets. Reads cache only — never fetches. */
final class PublicController
{
    public function home(array $args = []): void
    {
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $isPublicRoute = str_starts_with($requestPath, '/public');

        // Redirect logged-in users away from the bare home page to their feed.
        // The /public route is explicitly opt-in and never redirects.
        if (!$isPublicRoute && AuthService::currentUser() !== null) {
            header('Location: /feed');
            exit;
        }

        $allFeedUrl  = $isPublicRoute ? '/public' : '/';
        $catFeedBase = $isPublicRoute ? '/public/category/' : '/category/';

        $windowDays     = max(1, min(30, (int) ($_GET['days'] ?? 1)));
        $activeCategory = isset($args['slug']) && $args['slug'] !== '' ? $args['slug'] : null;

        // Categories for the filter bar.
        $categories = Database::query(
            'SELECT id, name, slug, color FROM source_categories ORDER BY sort_order'
        )->fetchAll();

        // Validate the category slug if one was provided.
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

        // Main feed: rss_atom + json_api sources only.
        // ransomlook and nvd adapter types go to the widget rail.
        $params   = [$windowDays];
        $catWhere = '';
        if ($activeCategory !== null) {
            $catWhere = 'AND c.slug = ?';
            $params[] = $activeCategory;
        }

        $articles = DedupService::group(Database::query(
            "SELECT a.id, a.title, a.url, a.summary, a.published_at, a.dedup_key,
                    s.name AS source_name, s.attribution_text, s.status AS source_status,
                    s.last_recovered_at,
                    c.name AS category, c.slug AS cat_slug, c.color
             FROM articles a
             JOIN sources s ON s.id = a.source_id
                 AND s.status IN ('active', 'degraded')
                 AND s.adapter_type IN ('rss_atom', 'json_api')
             LEFT JOIN source_categories c ON c.id = s.category_id
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             {$catWhere}
             ORDER BY a.published_at DESC
             LIMIT 60",
            $params
        )->fetchAll());

        // Ransomlook widget: respect the same time window as the main feed.
        $ransomlookItems = Database::query(
            "SELECT a.title, a.url, a.published_at
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type = 'ransomlook'
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY a.published_at DESC
               LIMIT 50",
            [$windowDays]
        )->fetchAll();

        // CVE widget: show the most recently catalogued entries regardless of time window.
        // CISA KEV dateAdded values reflect when CISA listed the CVE, not a publication date,
        // so filtering by the feed's time window would exclude all entries.
        $cveItems = Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at,
                    e.epss_score, e.percentile
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type IN ('nvd','cisa_kev')
             LEFT JOIN cve_epss e ON e.cve_id = a.guid
             ORDER BY a.published_at DESC
               LIMIT 50"
        )->fetchAll();

        // Page title: category name or 'Latest'.
        if ($activeCategory !== null) {
            $activeCatRow = array_values(array_filter($categories, fn($c) => $c['slug'] === $activeCategory));
            $title = $activeCatRow[0]['name'] ?? 'Category';
        } else {
            $title = 'Latest';
        }
        $activeNav = $isPublicRoute ? 'public' : 'feed';
        $showWidgets = true;

        $currentUser = AuthService::currentUser();
        $canBookmarkToKioju = false;
        if ($currentUser !== null) {
            $canBookmarkToKioju = KiojuService::hasApiKey((int) $currentUser['id']);
        }

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/feed/index.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    public function sources(array $args = []): void
    {
        $categorySlug = isset($_GET['category']) ? mb_substr(trim((string) $_GET['category']), 0, 64) : null;
        $searchQuery = $this->normalizeSourceSearchQuery($_GET['q'] ?? '');
        $sortKey = $this->normalizeSourceSort($_GET['sort'] ?? 'name');

        $categories = Database::query(
            'SELECT id, name, slug, color FROM source_categories ORDER BY sort_order'
        )->fetchAll();

        $activeCategory = $this->normalizeSourceCategory($categorySlug, $categories);

        $summary = Database::query(
            "SELECT
                COUNT(DISTINCT s.id) AS total_sources,
                COUNT(a.id) AS total_articles,
                SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS articles_24h,
                COUNT(DISTINCT CASE
                    WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN s.id
                    ELSE NULL
                END) AS active_sources_7d
             FROM sources s
             LEFT JOIN articles a ON a.source_id = s.id
             WHERE s.status IN ('active', 'degraded')
               AND s.adapter_type IN ('rss_atom', 'json_api')"
        )->fetch() ?: [];

        $params = [];
        $whereParts = [
            "s.status IN ('active', 'degraded')",
            "s.adapter_type IN ('rss_atom', 'json_api')",
        ];
        if ($activeCategory !== null) {
            $whereParts[] = 'c.slug = ?';
            $params[] = $activeCategory;
        }
        if ($searchQuery !== '') {
            $whereParts[] = 's.name LIKE ?';
            $params[] = '%' . $searchQuery . '%';
        }

        $orderSql = $this->sourceSortSql($sortKey);

        $sources = Database::query(
            "SELECT
                s.id,
                s.name,
                s.slug,
                s.homepage_url,
                s.adapter_type,
                s.status,
                s.last_success_at,
                s.last_recovered_at,
                c.name AS category_name,
                c.slug AS category_slug,
                COUNT(a.id) AS total_articles,
                                SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) AS articles_24h,
                                SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS articles_7d,
                                SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS articles_30d,
                MAX(a.published_at) AS latest_article_at
             FROM sources s
             LEFT JOIN source_categories c ON c.id = s.category_id
             LEFT JOIN articles a ON a.source_id = s.id
                         WHERE " . implode(' AND ', $whereParts) . "
             GROUP BY s.id, s.name, s.slug, s.homepage_url, s.adapter_type, s.status,
                      s.last_success_at, s.last_recovered_at, c.name, c.slug
                         ORDER BY {$orderSql}",
            $params
        )->fetchAll();

        $mostActive7d = Database::query(
            "SELECT
                                s.name AS source_name,
                                c.name AS category_name,
                                SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS article_count
                         FROM sources s
                         LEFT JOIN source_categories c ON c.id = s.category_id
                         LEFT JOIN articles a ON a.source_id = s.id
                         WHERE s.status IN ('active', 'degraded')
                             AND s.adapter_type IN ('rss_atom', 'json_api')
                         GROUP BY s.id, s.name, c.name
                         HAVING article_count > 0
                         ORDER BY article_count DESC, s.name ASC
                         LIMIT 10"
        )->fetchAll();

        $mostActive30d = Database::query(
            "SELECT
                                s.name AS source_name,
                                c.name AS category_name,
                                SUM(CASE WHEN a.published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS article_count
                         FROM sources s
                         LEFT JOIN source_categories c ON c.id = s.category_id
                         LEFT JOIN articles a ON a.source_id = s.id
                         WHERE s.status IN ('active', 'degraded')
                             AND s.adapter_type IN ('rss_atom', 'json_api')
                         GROUP BY s.id, s.name, c.name
                         HAVING article_count > 0
                         ORDER BY article_count DESC, s.name ASC
                         LIMIT 10"
        )->fetchAll();

        $freshnessLeaders = Database::query(
            "SELECT
                                s.name AS source_name,
                                c.name AS category_name,
                                MAX(a.published_at) AS latest_article_at
                         FROM sources s
                         LEFT JOIN source_categories c ON c.id = s.category_id
                         LEFT JOIN articles a ON a.source_id = s.id
                         WHERE s.status IN ('active', 'degraded')
                             AND s.adapter_type IN ('rss_atom', 'json_api')
                         GROUP BY s.id, s.name, c.name
                         HAVING latest_article_at IS NOT NULL
                         ORDER BY latest_article_at DESC, s.name ASC
                         LIMIT 10"
        )->fetchAll();

        $activeToday = Database::query(
            "SELECT COUNT(DISTINCT s.id) AS source_count
                         FROM sources s
                         JOIN articles a ON a.source_id = s.id
                         WHERE s.status IN ('active', 'degraded')
                             AND s.adapter_type IN ('rss_atom', 'json_api')
                             AND a.published_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
        )->fetch() ?: [];

        $staleSources = Database::query(
            "SELECT COUNT(*) AS source_count
                         FROM (
                                SELECT s.id, MAX(a.published_at) AS latest_article_at
                                FROM sources s
                                LEFT JOIN articles a ON a.source_id = s.id
                                WHERE s.status IN ('active', 'degraded')
                                    AND s.adapter_type IN ('rss_atom', 'json_api')
                                GROUP BY s.id
                         ) stale
                         WHERE stale.latest_article_at IS NULL
                                OR stale.latest_article_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetch() ?: [];

        $dailyActivityRows = Database::query(
            "SELECT
                                s.id AS source_id,
                                s.name AS source_name,
                                c.name AS category_name,
                                DATE(a.published_at) AS day_key,
                                COUNT(a.id) AS article_count
                         FROM sources s
                         LEFT JOIN source_categories c ON c.id = s.category_id
                         JOIN articles a ON a.source_id = s.id
                         WHERE " . implode(' AND ', $whereParts) . "
                             AND a.published_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                         GROUP BY s.id, s.name, c.name, DATE(a.published_at)
                         ORDER BY s.name ASC, day_key DESC",
            $params
        )->fetchAll();

        $dailyBreakdown = $this->buildDailyBreakdown($sources, $dailyActivityRows, 14, 20);
        $derivedMetrics = $this->buildDerivedSourceMetrics($dailyActivityRows, 30);

        $title = 'Sources';
        $seoTitle = 'Sources · Daybreak';
        $seoDescription = 'Browse all security news sources tracked by Daybreak, including archive depth and recent activity.';
        $activeNav = 'sources';
        $showWidgets = false;
        $showFilterBar = false;

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/page/sources.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    private function normalizeSourceSearchQuery(mixed $candidate): string
    {
        if (!is_scalar($candidate)) {
            return '';
        }

        return mb_substr(trim((string) $candidate), 0, 100);
    }

    private function normalizeSourceSort(mixed $candidate): string
    {
        if (!is_scalar($candidate)) {
            return 'total';
        }

        $sort = trim((string) $candidate);
        $allowed = ['total', 'recent_24h', 'recent_7d', 'recent_30d', 'latest', 'name'];
        return in_array($sort, $allowed, true) ? $sort : 'total';
    }

    private function sourceSortSql(string $sortKey): string
    {
        return match ($sortKey) {
            'recent_24h' => 'articles_24h DESC, total_articles DESC, s.name ASC',
            'recent_7d' => 'articles_7d DESC, total_articles DESC, s.name ASC',
            'recent_30d' => 'articles_30d DESC, total_articles DESC, s.name ASC',
            'latest' => 'latest_article_at DESC, total_articles DESC, s.name ASC',
            'name' => 's.name ASC',
            default => 'total_articles DESC, s.name ASC',
        };
    }

    private function buildDailyBreakdown(array $sources, array $dailyRows, int $days, int $limit): array
    {
        $dayKeys = [];
        $today = new \DateTimeImmutable('today');
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $dayKeys[] = $today->sub(new \DateInterval('P' . $offset . 'D'))->format('Y-m-d');
        }

        $countsBySource = [];
        foreach ($dailyRows as $row) {
            $sourceId = (int) ($row['source_id'] ?? 0);
            $dayKey = (string) ($row['day_key'] ?? '');
            if ($sourceId <= 0 || $dayKey === '') {
                continue;
            }
            $countsBySource[$sourceId][$dayKey] = (int) ($row['article_count'] ?? 0);
        }

        $rows = [];
        foreach (array_slice($sources, 0, $limit) as $source) {
            $sourceId = (int) ($source['id'] ?? 0);
            $cells = [];
            $windowTotal = 0;
            foreach ($dayKeys as $dayKey) {
                $count = (int) ($countsBySource[$sourceId][$dayKey] ?? 0);
                $cells[] = [
                    'day_key' => $dayKey,
                    'count' => $count,
                ];
                $windowTotal += $count;
            }

            $rows[] = [
                'source_name' => (string) ($source['name'] ?? ''),
                'category_name' => isset($source['category_name']) ? (string) $source['category_name'] : null,
                'window_total' => $windowTotal,
                'cells' => $cells,
            ];
        }

        return [
            'day_keys' => $dayKeys,
            'rows' => $rows,
        ];
    }

    private function buildDerivedSourceMetrics(array $dailyRows, int $days): array
    {
        $today = new \DateTimeImmutable('today');
        $dayKeys = [];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $dayKeys[] = $today->sub(new \DateInterval('P' . $offset . 'D'))->format('Y-m-d');
        }

        $bySource = [];
        foreach ($dailyRows as $row) {
            $sourceId = (int) ($row['source_id'] ?? 0);
            if ($sourceId <= 0) {
                continue;
            }

            if (!isset($bySource[$sourceId])) {
                $bySource[$sourceId] = [
                    'source_name' => (string) ($row['source_name'] ?? ''),
                    'category_name' => isset($row['category_name']) ? (string) $row['category_name'] : null,
                    'counts' => [],
                ];
            }

            $dayKey = (string) ($row['day_key'] ?? '');
            if ($dayKey !== '') {
                $bySource[$sourceId]['counts'][$dayKey] = (int) ($row['article_count'] ?? 0);
            }
        }

        $metrics = [];
        foreach ($bySource as $sourceData) {
            $total = 0;
            $activeDays = 0;
            $longestStreak = 0;
            $currentStreak = 0;
            $maxSingleDay = 0;

            foreach ($dayKeys as $dayKey) {
                $count = (int) ($sourceData['counts'][$dayKey] ?? 0);
                $total += $count;
                if ($count > 0) {
                    $activeDays++;
                    $currentStreak++;
                    $longestStreak = max($longestStreak, $currentStreak);
                    $maxSingleDay = max($maxSingleDay, $count);
                } else {
                    $currentStreak = 0;
                }
            }

            if ($total === 0) {
                continue;
            }

            $metrics[] = [
                'source_name' => $sourceData['source_name'],
                'category_name' => $sourceData['category_name'],
                'total_articles_30d' => $total,
                'active_days_30d' => $activeDays,
                'avg_articles_per_active_day' => $activeDays > 0 ? round($total / $activeDays, 1) : 0.0,
                'longest_streak_30d' => $longestStreak,
                'max_single_day_30d' => $maxSingleDay,
            ];
        }

        $consistencyLeaders = $metrics;
        usort($consistencyLeaders, static function (array $left, array $right): int {
            return [$right['active_days_30d'], $right['longest_streak_30d'], $left['avg_articles_per_active_day'], strcmp($left['source_name'], $right['source_name'])]
                <=> [$left['active_days_30d'], $left['longest_streak_30d'], $right['avg_articles_per_active_day'], strcmp($right['source_name'], $left['source_name'])];
        });

        $quietReliable = array_values(array_filter($metrics, static function (array $metric): bool {
            return $metric['active_days_30d'] >= 4 && $metric['total_articles_30d'] <= 20;
        }));
        usort($quietReliable, static function (array $left, array $right): int {
            return [$right['active_days_30d'], $left['total_articles_30d'], strcmp($left['source_name'], $right['source_name'])]
                <=> [$left['active_days_30d'], $right['total_articles_30d'], strcmp($right['source_name'], $left['source_name'])];
        });

        $burstySources = $metrics;
        usort($burstySources, static function (array $left, array $right): int {
            return [$right['max_single_day_30d'], $right['total_articles_30d'], strcmp($left['source_name'], $right['source_name'])]
                <=> [$left['max_single_day_30d'], $left['total_articles_30d'], strcmp($right['source_name'], $left['source_name'])];
        });

        return [
            'consistency_leaders' => array_slice($consistencyLeaders, 0, 10),
            'quiet_reliable' => array_slice($quietReliable, 0, 10),
            'bursty_sources' => array_slice($burstySources, 0, 10),
        ];
    }

    private function normalizeSourceCategory(?string $candidate, array $categories): ?string
    {
        if (!is_string($candidate) || $candidate === '') {
            return null;
        }

        foreach ($categories as $category) {
            if (($category['slug'] ?? null) === $candidate) {
                return $candidate;
            }
        }

        return null;
    }
}
