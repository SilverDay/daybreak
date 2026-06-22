<?php

declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Database;
use Daybreak\Adapter\SourceAdapter;
use Daybreak\Adapter\RssAtomAdapter;
use Daybreak\Adapter\RansomlookAdapter;
use Daybreak\Adapter\NvdAdapter;
use Daybreak\Adapter\CisaKevAdapter;
use Daybreak\Adapter\GitHubAdvisoryAdapter;
use Daybreak\Adapter\JsonApiAdapter;
use Throwable;

/**
 * Orchestrates one fetch cycle: resolve adapter -> fetch -> upsert new articles
 * -> update source health -> write fetch_log. Never throws out of runSource();
 * one bad source must not abort the run.
 */
final class AggregationService
{
    private const FAIL_DEGRADE = 3;   // consecutive failures -> degraded
    private const FAIL_DISABLE = 8;   // -> auto_disabled

    /** @var SourceAdapter[] */
    private array $adapters;

    public function __construct(
        private readonly FeedFetcher $fetcher,
        private readonly WebhookService $webhooks,
    ) {
        $this->adapters = [new RssAtomAdapter(), new RansomlookAdapter(), new NvdAdapter(), new CisaKevAdapter(), new JsonApiAdapter(), new GitHubAdvisoryAdapter()];
    }

    /** @return array{ok:int,errors:int} */
    public function runDue(bool $force = false): array
    {
        $this->webhooks->retryFailed();

        $base = "SELECT s.*, c.slug AS category_slug
                  FROM sources s LEFT JOIN source_categories c ON c.id = s.category_id
                  WHERE s.status IN ('active','degraded','pending')";
        $sql = $force ? $base : $base . ' AND (s.next_fetch_at IS NULL OR s.next_fetch_at <= NOW())';
        $sources = Database::query($sql)->fetchAll();

        $ok = 0;
        $err = 0;
        foreach ($sources as $source) {
            $this->runSource($source) ? $ok++ : $err++;
        }
        return ['ok' => $ok, 'errors' => $err];
    }

    public function runSource(array $source): bool
    {
        $started = microtime(true);
        $adapter = $this->adapterFor((string) $source['adapter_type']);
        if ($adapter === null) {
            $this->log((int) $source['id'], 'error', null, 0, 0, $started, 'no adapter for ' . $source['adapter_type']);
            return false;
        }

        try {
            $ua = isset($source['user_agent_override']) && $source['user_agent_override'] !== ''
                ? (string) $source['user_agent_override']
                : '';
            $fetcher = $ua !== '' ? new FeedFetcher($ua) : $this->fetcher;
            $result = $adapter->fetch($source, $fetcher);

            // Treat 202 (Cloudflare soft-block) and 4xx/5xx as failures so that
            // consecutive_failures increments and sources can be degraded/auto-disabled.
            $http = $result->httpStatus;
            if (!$result->notModified && ($http === 202 || ($http >= 400 && $http !== 304))) {
                $msg = "HTTP {$http}";
                $this->markFailure($source, $msg);
                $this->log((int) $source['id'], 'error', $http, 0, 0, $started, $msg);
                return false;
            }

            $new      = 0;
            $newItems = [];
            if (!$result->notModified) {
                foreach ($result->items as $item) {
                    $inserted = $this->upsert((int) $source['id'], $item);
                    $new += $inserted;
                    if ($inserted === 1) {
                        $newItems[] = $item;
                    }
                }
            }
            $this->webhooks->dispatch($source, $newItems);
            $this->markSuccess($source, $result->etag, $result->lastModified);
            $this->log((int) $source['id'], $result->notModified ? 'not_modified' : 'ok', $result->httpStatus, count($result->items), $new, $started, null);
            return true;
        } catch (Throwable $e) {
            $this->markFailure($source, $e->getMessage());
            $this->log((int) $source['id'], 'error', null, 0, 0, $started, substr($e->getMessage(), 0, 500));
            return false;
        }
    }

    private function adapterFor(string $type): ?SourceAdapter
    {
        foreach ($this->adapters as $a) {
            if ($a->supports($type)) {
                return $a;
            }
        }
        return null;
    }

    private function upsert(int $sourceId, \Daybreak\Adapter\NormalizedItem $item): int
    {
        $dedup = $this->dedupKey($item);
        $stmt = Database::query(
            'INSERT INTO articles (source_id, guid, title, url, summary, published_at, dedup_key)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary), published_at = VALUES(published_at)',
            [
                $sourceId,
                mb_substr($item->guid, 0, 500),
                mb_substr($item->title, 0, 500),
                mb_substr($item->url, 0, 1000),
                $item->summary,
                $item->publishedAt?->format('Y-m-d H:i:s'),
                $dedup,
            ]
        );
        return $stmt->rowCount() === 1 ? 1 : 0; // 1 = insert, 2 = update (rowCount), 0 = unchanged
    }

    private function dedupKey(\Daybreak\Adapter\NormalizedItem $item): ?string
    {
        $titleFingerprint = $this->titleFingerprint($item->title);
        $dateBucket = $item->publishedAt?->format('Y-m-d') ?? '';

        if ($titleFingerprint !== '') {
            return substr(hash('sha256', 'title|' . $titleFingerprint . '|date|' . $dateBucket), 0, 40);
        }

        $urlFingerprint = $this->urlFingerprint($item->url);
        if ($urlFingerprint !== '') {
            return substr(hash('sha256', 'url|' . $urlFingerprint . '|date|' . $dateBucket), 0, 40);
        }

        return null;
    }

    private function titleFingerprint(string $title): string
    {
        $normalized = html_entity_decode(mb_strtolower($title), ENT_QUOTES, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';
        $normalized = trim($normalized);
        if ($normalized === '') {
            return '';
        }

        $stopWords = [
            'a',
            'an',
            'and',
            'are',
            'as',
            'at',
            'be',
            'by',
            'for',
            'from',
            'in',
            'into',
            'is',
            'it',
            'of',
            'on',
            'or',
            'that',
            'the',
            'their',
            'this',
            'to',
            'via',
            'with',
        ];
        $stopWordMap = array_fill_keys($stopWords, true);

        $tokens = preg_split('/\s+/u', $normalized) ?: [];
        $significant = [];
        foreach ($tokens as $token) {
            if ($token === '' || isset($stopWordMap[$token])) {
                continue;
            }
            if (mb_strlen($token) < 3 && !preg_match('/^cve-\d{4}-\d+$/', $token)) {
                continue;
            }
            $significant[] = $token;
            if (count($significant) === 10) {
                break;
            }
        }

        if ($significant === []) {
            $significant = array_slice($tokens, 0, 10);
        }

        return implode(' ', $significant);
    }

    private function urlFingerprint(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $host = mb_strtolower((string) $parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        return $path !== '' ? $host . '/' . $path : $host;
    }

    private function markSuccess(array $source, ?string $etag, ?string $lastModified): void
    {
        Database::query(
            "UPDATE sources SET status = IF(status IN ('degraded','pending'),'active',status),
                consecutive_failures = 0, last_fetch_at = NOW(), last_success_at = NOW(),
                last_recovered_at = IF(status='degraded', NOW(), last_recovered_at),
                last_error = NULL, etag = ?, last_modified_hdr = ?,
                next_fetch_at = DATE_ADD(NOW(), INTERVAL fetch_interval_min MINUTE)
             WHERE id = ?",
            [$etag, $lastModified, (int) $source['id']]
        );
    }

    private function markFailure(array $source, string $error): void
    {
        $failures = (int) $source['consecutive_failures'] + 1;
        $status = $failures >= self::FAIL_DISABLE ? 'auto_disabled'
            : ($failures >= self::FAIL_DEGRADE ? 'degraded' : $source['status']);
        Database::query(
            "UPDATE sources SET consecutive_failures = ?, status = ?, last_fetch_at = NOW(),
                last_error = ?, next_fetch_at = DATE_ADD(NOW(), INTERVAL fetch_interval_min MINUTE)
             WHERE id = ?",
            [$failures, $status, substr($error, 0, 500), (int) $source['id']]
        );
    }

    private function log(int $sourceId, string $status, ?int $http, int $found, int $new, float $started, ?string $error): void
    {
        Database::query(
            'INSERT INTO fetch_log (source_id, status, http_status, items_found, items_new, duration_ms, error)
             VALUES (?,?,?,?,?,?,?)',
            [$sourceId, $status, $http, $found, $new, (int) ((microtime(true) - $started) * 1000), $error]
        );
    }
}
