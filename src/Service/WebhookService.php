<?php

declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Adapter\NormalizedItem;
use Daybreak\Database;

/**
 * Delivers new articles to user-configured outbound webhooks (Slack, Discord, generic HTTP).
 *
 * Called from AggregationService after each successful source fetch. Runs synchronously
 * in the cron process — delivery is fast enough at self-hosted article volumes.
 *
 * Filter semantics (filter_json = {"terms":[...],"categories":[...]}):
 *   - Neither set  → match all articles
 *   - Terms only   → match if any term appears in title or summary (case-insensitive)
 *   - Categories only → match if source category slug is in the list
 *   - Both set     → AND: must satisfy both the term check AND the category check
 *
 * SSRF guard is applied inside FetchClient::postJson() on every delivery attempt.
 */
final class WebhookService
{
    public function __construct(private readonly FetchClient $fetcher) {}

    /**
     * Deliver new articles from one source to all matching active webhooks.
     *
     * @param array{id:int,name:string,category_slug:?string} $source
     * @param NormalizedItem[] $newItems Articles that were INSERT-ed this run (not skipped by ON DUPLICATE KEY)
     */
    public function dispatch(array $source, array $newItems): void
    {
        if ($newItems === []) {
            return;
        }

        $webhooks = Database::query(
            'SELECT id, user_id, url, format, filter_json FROM user_webhooks WHERE active = 1'
        )->fetchAll();

        if ($webhooks === []) {
            return;
        }

        foreach ($webhooks as $wh) {
            foreach ($newItems as $item) {
                if (!$this->matches($wh, $item, $source)) {
                    continue;
                }

                $payload = match ($wh['format']) {
                    'slack'   => $this->slackPayload($item, $source['name']),
                    'discord' => $this->discordPayload($item, $source['name']),
                    default   => $this->genericPayload($item, $source['name']),
                };

                $this->deliver((int) $wh['id'], $wh['url'], $payload, $item, 1);
            }
        }
    }

    /**
     * Retry deliveries that failed on the previous cron run (status='failed', attempt=1).
     * Only retries entries from the last 24 hours to bound the retry window.
     */
    public function retryFailed(): void
    {
        $rows = Database::query(
            "SELECT wl.id, wl.webhook_id, wl.article_url, wl.article_title
             FROM webhook_log wl
             WHERE wl.status = 'failed' AND wl.attempt = 1
               AND wl.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetchAll();

        if ($rows === []) {
            return;
        }

        // Load the webhooks we need (may have been deleted since the failed run).
        $ids = array_unique(array_map(static fn($r) => (int) $r['webhook_id'], $rows));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $webhooks = Database::query(
            "SELECT id, url, format FROM user_webhooks WHERE id IN ({$placeholders}) AND active = 1",
            $ids
        )->fetchAll();
        $webhookMap = array_column($webhooks, null, 'id');

        foreach ($rows as $row) {
            $wh = $webhookMap[(int) $row['webhook_id']] ?? null;
            if ($wh === null) {
                // Webhook deleted or deactivated — mark as retry_failed without attempting.
                Database::query(
                    "UPDATE webhook_log SET status = 'retry_failed', attempt = 2 WHERE id = ?",
                    [(int) $row['id']]
                );
                continue;
            }

            // Reconstruct a minimal NormalizedItem for the payload builder.
            $item = new NormalizedItem(
                guid:    hash('sha256', $row['article_url']),
                title:   $row['article_title'],
                url:     $row['article_url'],
                summary: null,
            );

            $payload = match ($wh['format']) {
                'slack'   => $this->slackPayload($item, ''),
                'discord' => $this->discordPayload($item, ''),
                default   => $this->genericPayload($item, ''),
            };

            // Deliver; mark the original log row as retry_ok or retry_failed.
            [$status, $error] = $this->attemptDelivery($wh['url'], $payload);
            Database::query(
                "UPDATE webhook_log SET status = ?, http_status = ?, attempt = 2, error = ? WHERE id = ?",
                [
                    $status >= 200 && $status < 300 ? 'retry_ok' : 'retry_failed',
                    $status ?: null,
                    $error,
                    (int) $row['id'],
                ]
            );
        }
    }

    /**
     * @param array{id:int,filter_json:?string} $webhook
     * @param array{name:string,category_slug:?string} $source
     */
    private function matches(array $webhook, NormalizedItem $item, array $source): bool
    {
        $filter = [];
        if ($webhook['filter_json'] !== null && $webhook['filter_json'] !== '') {
            $filter = json_decode($webhook['filter_json'], true) ?? [];
        }

        $terms = array_filter((array) ($filter['terms'] ?? []));
        $cats  = array_filter((array) ($filter['categories'] ?? []));

        if ($terms === [] && $cats === []) {
            return true;
        }

        $termMatch = $terms === [];
        if (!$termMatch) {
            $haystack = mb_strtolower($item->title . ' ' . ($item->summary ?? ''));
            foreach ($terms as $t) {
                if (str_contains($haystack, mb_strtolower((string) $t))) {
                    $termMatch = true;
                    break;
                }
            }
        }

        $catMatch = $cats === [];
        if (!$catMatch) {
            $slug = (string) ($source['category_slug'] ?? '');
            foreach ($cats as $c) {
                if ($slug === (string) $c) {
                    $catMatch = true;
                    break;
                }
            }
        }

        return $termMatch && $catMatch;
    }

    private function slackPayload(NormalizedItem $item, string $sourceName): string
    {
        $title   = mb_substr($item->title, 0, 150);
        $summary = $item->summary !== null ? mb_substr($item->summary, 0, 300) : '';
        $footer  = $sourceName !== '' ? 'Daybreak · ' . $sourceName : 'Daybreak';

        return json_encode([
            'text'        => "*{$title}*" . ($sourceName !== '' ? " — {$sourceName}" : ''),
            'attachments' => [[
                'color'      => '#c0392b',
                'title'      => $title,
                'title_link' => $item->url,
                'text'       => $summary,
                'footer'     => $footer,
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function discordPayload(NormalizedItem $item, string $sourceName): string
    {
        $title   = mb_substr($item->title, 0, 256);
        $summary = $item->summary !== null ? mb_substr($item->summary, 0, 4096) : '';

        return json_encode([
            'username' => 'Daybreak',
            'embeds'   => [[
                'title'       => $title,
                'url'         => $item->url,
                'description' => $summary,
                'color'       => 0xc0392b,
                'footer'      => ['text' => $sourceName !== '' ? $sourceName : 'Daybreak'],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function genericPayload(NormalizedItem $item, string $sourceName): string
    {
        return json_encode([
            'event'   => 'new_article',
            'article' => [
                'title'        => $item->title,
                'url'          => $item->url,
                'summary'      => $item->summary,
                'source'       => $sourceName,
                'published_at' => $item->publishedAt?->format('Y-m-d\TH:i:s\Z'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Attempt one delivery and log the result to webhook_log. */
    private function deliver(int $webhookId, string $url, string $payload, NormalizedItem $item, int $attempt): void
    {
        [$status, $error] = $this->attemptDelivery($url, $payload);

        Database::query(
            'INSERT INTO webhook_log (webhook_id, article_url, article_title, status, http_status, attempt, error)
             VALUES (?,?,?,?,?,?,?)',
            [
                $webhookId,
                mb_substr($item->url,   0, 1000),
                mb_substr($item->title, 0, 500),
                $status >= 200 && $status < 300 ? 'ok' : 'failed',
                $status ?: null,
                $attempt,
                $error,
            ]
        );
    }

    /**
     */
    private function attemptDelivery(string $url, string $payload): array
    {
        try {
            $res = $this->fetcher->postJson($url, $payload);
            $ok  = $res['status'] >= 200 && $res['status'] < 300;
            return [
                $res['status'],
                $ok ? null : mb_substr('HTTP ' . $res['status'] . ': ' . strip_tags($res['body']), 0, 500),
            ];
        } catch (\Throwable $e) {
            return [0, mb_substr($e->getMessage(), 0, 500)];
        }
    }
}
