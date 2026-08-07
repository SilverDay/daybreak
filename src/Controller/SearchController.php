<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Html;
use Daybreak\Service\AuthService;
use Daybreak\Service\DedupService;
use Daybreak\Service\KiojuService;

/**
 * Article search across cached articles.
 * Supports headline/summary search with optional filters for category, source, and time window.
 * Personalised results for authenticated users (respects source preferences).
 */
final class SearchController
{
    public function search(array $args = []): void
    {
        $q              = mb_substr(trim($_GET['q'] ?? ''), 0, 500);
        $windowDays     = max(1, min(90, (int) ($_GET['days'] ?? 30)));
        $categorySlug   = isset($_GET['category']) ? mb_substr(trim($_GET['category']), 0, 64) : null;
        $sourceId       = isset($_GET['source']) ? max(0, (int) $_GET['source']) : null;
        $currentUser    = AuthService::currentUser();
        $userId         = $currentUser ? (int) $currentUser['id'] : null;

        // Categories for filter controls.
        $categories = Database::query(
            'SELECT id, name, slug, color FROM source_categories ORDER BY sort_order'
        )->fetchAll();

        // Validate category if provided.
        if ($categorySlug !== null && $categorySlug !== '') {
            $valid = false;
            foreach ($categories as $cat) {
                if ($cat['slug'] === $categorySlug) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                $categorySlug = null;
            }
        } else {
            $categorySlug = null;
        }

        $articles = [];
        $searched = false;
        $message  = '';

        if ($q !== '') {
            $searched = true;

            // Build search query for both public and personalized contexts.
            $params = [];
            $where = [
                "(a.title LIKE ? OR a.summary LIKE ?)",
                "a.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
            ];
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = $windowDays;

            // Category filter.
            if ($categorySlug !== null && $categorySlug !== '') {
                $where[] = 'c.slug = ?';
                $params[] = $categorySlug;
            }

            // Source filter.
            if ($sourceId > 0) {
                $where[] = 's.id = ?';
                $params[] = $sourceId;
            }

            // User source preference filter (personalized only).
            $userSourceJoin = '';
            if ($userId !== null) {
                // For personalized search: exclude sources user has opted out of.
                $userSourceJoin = 'LEFT JOIN user_sources us ON us.source_id = s.id AND us.user_id = ?';
                $where[] = '(us.enabled IS NULL OR us.enabled = 1)';
                array_unshift($params, $userId);
            }

            $fromJoin =
                "articles a
                JOIN sources s ON s.id = a.source_id
                    AND s.status IN ('active', 'degraded')
                    AND s.adapter_type IN ('rss_atom', 'json_api')
                LEFT JOIN source_categories c ON c.id = s.category_id
                {$userSourceJoin}";
            $whereSql = implode(' AND ', $where);

            $dupes      = DedupService::findDuplicates($fromJoin, $whereSql, $params);
            $excludeIds = $dupes['excludeIds'];
            $excludeClause = $excludeIds !== []
                ? 'AND a.id NOT IN (' . implode(',', array_fill(0, count($excludeIds), '?')) . ')'
                : '';

            $sql = "
                SELECT a.id, a.title, a.url, a.summary, a.published_at, a.dedup_key,
                       s.id AS source_id, s.name AS source_name, s.attribution_text,
                       c.name AS category, c.slug AS cat_slug, c.color
                FROM {$fromJoin}
                WHERE {$whereSql}
                {$excludeClause}
                ORDER BY a.published_at DESC, a.id DESC
                LIMIT 100
            ";

            $primaries = Database::query($sql, array_merge($params, $excludeIds))->fetchAll();
            $articles  = DedupService::attachAlsoBy($primaries, $dupes['byKey']);

            if (count($articles) === 0) {
                $message = "No results found for \"" . Html::e($q) . "\".";
            } else {
                $message = count($articles) . ' result' . (count($articles) === 1 ? '' : 's') . " for \"" . Html::e($q) . "\".";
            }
        } else {
            $message = 'Enter a search term to find articles.';
        }

        // Page title and nav.
        $title     = 'Search';
        $activeNav = 'search';

        // Feed-like variables for layout.
        $windowMode     = $windowDays;
        $windowOptions  = [1 => 'Last 24h', 7 => 'Last 7 days', 30 => 'Last 30 days', 60 => 'Last 60 days', 90 => 'Last 90 days'];
        $activeCategory = $categorySlug;
        $allFeedUrl     = '/search';
        $catFeedBase    = '/search?category=';
        $showWidgets    = false;

        $canBookmarkToKioju = false;
        if ($currentUser !== null) {
            $canBookmarkToKioju = KiojuService::hasApiKey($userId);
        }

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/search/index.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }
}
