<?php
declare(strict_types=1);

/** Cron entry point. Usage: php bin/fetch.php [--force] [--source=slug] */

require __DIR__ . '/../src/bootstrap.php';

use Daybreak\Database;
use Daybreak\Service\FeedFetcher;
use Daybreak\Service\WebhookService;
use Daybreak\Service\AggregationService;
use Daybreak\Service\EpssService;

$opts   = getopt('', ['force', 'source:']);
$force  = isset($opts['force']);
$slug   = $opts['source'] ?? null;

// Global lock to prevent overlapping runs.
// GET_LOCK is session-scoped: MariaDB auto-releases it if this process dies.
$acquired = Database::query("SELECT GET_LOCK('daybreak_fetch', 0)")->fetchColumn();
if ($acquired !== '1' && $acquired !== 1) {
    fwrite(STDERR, "another fetch run is in progress\n");
    exit(1);
}

$fetcher  = new FeedFetcher();
$webhooks = new WebhookService($fetcher);
$svc      = new AggregationService($fetcher, $webhooks);
$epss     = new EpssService($fetcher);

if ($slug !== null) {
    $source = Database::query(
        'SELECT s.*, c.slug AS category_slug
         FROM sources s LEFT JOIN source_categories c ON c.id = s.category_id
         WHERE s.slug = ?',
        [$slug]
    )->fetch();
    if (!$source) {
        fwrite(STDERR, "source not found: {$slug}\n");
        exit(1);
    }
    $ok = $svc->runSource($source);
    $epss->refreshDue();
    echo $ok ? "ok\n" : "error\n";
} else {
    $r = $svc->runDue($force);
    $epss->refreshDue();
    echo "done: {$r['ok']} ok, {$r['errors']} errors\n";
}

Database::query("SELECT RELEASE_LOCK('daybreak_fetch')");
