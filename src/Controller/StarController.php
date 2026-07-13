<?php

declare(strict_types=1);

namespace Daybreak\Controller;

use Daybreak\Database;
use Daybreak\Security\Csrf;
use Daybreak\Service\AuthService;

final class StarController
{
    public function toggle(array $args = []): void
    {
        AuthService::requireAuth();
        Csrf::check();

        $userId    = (int) AuthService::currentUser()['id'];
        $articleId = (int) ($_POST['article_id'] ?? 0);

        if ($articleId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid article ID']);
            return;
        }

        $article = Database::query(
            'SELECT a.id, a.url, a.title, a.published_at, s.name AS source_name
             FROM articles a JOIN sources s ON s.id = a.source_id WHERE a.id = ?',
            [$articleId]
        )->fetch();

        if (!$article) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Article not found']);
            return;
        }

        $exists = Database::query(
            'SELECT id FROM user_starred_articles WHERE user_id = ? AND article_id = ?',
            [$userId, $articleId]
        )->fetch();

        if ($exists) {
            Database::query(
                'DELETE FROM user_starred_articles WHERE user_id = ? AND article_id = ?',
                [$userId, $articleId]
            );
            $starred = false;
        } else {
            Database::query(
                'INSERT INTO user_starred_articles
                 (user_id, article_id, url, title, source_name, published_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$userId, $articleId, $article['url'], $article['title'],
                 $article['source_name'], $article['published_at']]
            );
            $starred = true;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['starred' => $starred]);
    }

    public function list(array $args = []): void
    {
        AuthService::requireAuth();
        $userId = (int) AuthService::currentUser()['id'];
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 20;

        $total = (int) Database::query(
            'SELECT COUNT(*) FROM user_starred_articles WHERE user_id = ?', [$userId]
        )->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $limit));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $limit;

        $starred = Database::query(
            'SELECT sa.article_id, sa.url, sa.title, sa.source_name, sa.published_at, sa.starred_at,
                    (a.id IS NULL) AS detached
             FROM user_starred_articles sa
             LEFT JOIN articles a ON a.id = sa.article_id
             WHERE sa.user_id = ?
             ORDER BY sa.starred_at DESC
             LIMIT ? OFFSET ?',
            [$userId, $limit, $offset]
        )->fetchAll();

        $ransomlookItems = Database::query(
            "SELECT a.title, a.url, a.published_at FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type = 'ransomlook'
             WHERE a.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY a.published_at DESC LIMIT 10"
        )->fetchAll();

        $cveItems = Database::query(
            "SELECT a.title, a.url, a.summary, a.published_at,
                    e.epss_score, e.percentile
             FROM articles a
             JOIN sources s ON s.id = a.source_id AND s.adapter_type IN ('nvd','cisa_kev')
             LEFT JOIN cve_epss e ON e.cve_id = a.guid
             ORDER BY a.published_at DESC LIMIT 10"
        )->fetchAll();

        $defaultWidgetSlots = [
            1 => [
                'kind'  => 'default_ransomlook',
                'title' => 'Ransomware Activity',
                'items' => $ransomlookItems,
            ],
            2 => [
                'kind'  => 'default_cves',
                'title' => 'Recent CVEs',
                'items' => $cveItems,
            ],
        ];

        $configuredSlotRows = Database::query(
            "SELECT u.slot, u.source_id,
                    s.id AS source_exists, s.name AS source_name, s.attribution_text
             FROM user_widget_sources u
             LEFT JOIN sources s ON s.id = u.source_id
               AND s.status IN ('active', 'degraded')
               AND s.adapter_type IN ('rss_atom', 'json_api', 'cisa_kev')
             WHERE u.user_id = ? AND u.slot IN (1,2)
             ORDER BY u.slot ASC",
            [$userId]
        )->fetchAll();

        $overrides = [];
        foreach ($configuredSlotRows as $row) {
            $slot = (int) ($row['slot'] ?? 0);
            if ($slot !== 1 && $slot !== 2) {
                continue;
            }

            $sourceId = isset($row['source_id']) ? (int) $row['source_id'] : null;
            if ($sourceId === null || !isset($row['source_exists'])) {
                continue;
            }

            $items = Database::query(
                'SELECT title, url, published_at FROM articles WHERE source_id = ? ORDER BY published_at DESC LIMIT 10',
                [$sourceId]
            )->fetchAll();

            $overrides[$slot] = [
                'kind'        => 'custom',
                'title'       => (string) ($row['source_name'] ?? 'Source Updates'),
                'attribution' => (string) ($row['attribution_text'] ?? ''),
                'items'       => $items,
            ];
        }

        $widgetSlots = $this->mergeWidgetSlots($defaultWidgetSlots, $overrides);

        $title       = 'Starred';
        $activeNav   = 'starred';
        $showWidgets = true;

        header('Content-Type: text/html; charset=utf-8');
        include DB_ROOT . '/src/View/layout.php';
        include DB_ROOT . '/src/View/starred/index.php';
        include DB_ROOT . '/src/View/layout_end.php';
    }

    /** @param array<int, array<string, mixed>> $defaults @param array<int, array<string, mixed>> $overrides */
    private function mergeWidgetSlots(array $defaults, array $overrides): array
    {
        $slots = $defaults;
        foreach ($overrides as $slot => $payload) {
            if (($slot === 1 || $slot === 2) && is_array($payload)) {
                $slots[$slot] = $payload;
            }
        }

        ksort($slots);
        return array_values($slots);
    }
}
