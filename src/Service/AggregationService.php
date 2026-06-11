<?php
declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Database;
use Daybreak\Adapter\SourceAdapter;
use Daybreak\Adapter\RssAtomAdapter;
use Daybreak\Adapter\RansomlookAdapter;
use Daybreak\Adapter\NvdAdapter;
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

    public function __construct(private readonly FeedFetcher $fetcher)
    {
        $this->adapters = [new RssAtomAdapter(), new RansomlookAdapter(), new NvdAdapter(), new JsonApiAdapter()];
    }

    /** @return array{ok:int,errors:int} */
    public function runDue(bool $force = false): array
    {
        $sql = $force
            ? "SELECT * FROM sources WHERE status IN ('active','degraded')"
            : "SELECT * FROM sources WHERE status IN ('active','degraded') AND (next_fetch_at IS NULL OR next_fetch_at <= NOW())";
        $sources = Database::query($sql)->fetchAll();

        $ok = 0; $err = 0;
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
            $result = $adapter->fetch($source, $this->fetcher);

            // Treat 202 (Cloudflare soft-block) and 4xx/5xx as failures so that
            // consecutive_failures increments and sources can be degraded/auto-disabled.
            $http = $result->httpStatus;
            if (!$result->notModified && ($http === 202 || ($http >= 400 && $http !== 304))) {
                $msg = "HTTP {$http}";
                $this->markFailure($source, $msg);
                $this->log((int) $source['id'], 'error', $http, 0, 0, $started, $msg);
                return false;
            }

            $new = 0;
            if (!$result->notModified) {
                foreach ($result->items as $item) {
                    $new += $this->upsert((int) $source['id'], $item);
                }
            }
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
        $dedup = substr(hash('sha256', preg_replace('/[^a-z0-9]+/', '', strtolower($item->title)) ?? ''), 0, 40);
        $stmt = Database::query(
            'INSERT INTO articles (source_id, guid, title, url, summary, published_at, dedup_key)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), summary = VALUES(summary)',
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

    private function markSuccess(array $source, ?string $etag, ?string $lastModified): void
    {
        Database::query(
            "UPDATE sources SET status = IF(status='degraded','active',status),
                consecutive_failures = 0, last_fetch_at = NOW(), last_success_at = NOW(),
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
