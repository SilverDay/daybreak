<?php
declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Html;

/** Public news page: main feed + ransomlook/CVE widgets. Reads cache only — never fetches. */
final class PublicController
{
    public function home(array $args = []): void
    {
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
                if ($cat['slug'] === $activeCategory) { $valid = true; break; }
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

        $articles = Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at,
                    s.name AS source_name, s.attribution_text,
                    c.name AS category, c.slug AS cat_slug, c.color
             FROM articles a
             JOIN sources s ON s.id = a.source_id
                 AND s.status IN ('active', 'degraded')
                 AND s.adapter_type IN ('rss_atom', 'json_api')
             LEFT JOIN source_categories c ON c.id = s.category_id
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             {$catWhere}
             ORDER BY a.published_at DESC
             LIMIT 100",
            $params
        )->fetchAll();

        // Ransomlook widget: last 7 days, 20 most recent.
        $ransomlookItems = Database::query(
            "SELECT a.title, a.url, a.published_at
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type = 'ransomlook'
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY a.published_at DESC
             LIMIT 20"
        )->fetchAll();

        // CVE widget: last 7 days, 15 most recent.
        $cveItems = Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type = 'nvd'
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY a.published_at DESC
             LIMIT 15"
        )->fetchAll();

        // Page title: category name or 'Latest'.
        if ($activeCategory !== null) {
            $activeCatRow = array_values(array_filter($categories, fn($c) => $c['slug'] === $activeCategory));
            $title = $activeCatRow[0]['name'] ?? 'Category';
        } else {
            $title = 'Latest';
        }
        $activeNav = 'feed';

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/feed/index.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }
}
