<?php
declare(strict_types=1);

/** Cron entry point. Usage: php bin/fetch.php [--force] [--source=slug] */

require __DIR__ . '/../src/bootstrap.php';

use Daybreak\Database;
use Daybreak\Service\FeedFetcher;
use Daybreak\Service\AggregationService;

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

$svc = new AggregationService(new FeedFetcher());

if ($slug !== null) {
    $source = Database::query('SELECT * FROM sources WHERE slug = ?', [$slug])->fetch();
    if (!$source) {
        fwrite(STDERR, "source not found: {$slug}\n");
        exit(1);
    }
    $ok = $svc->runSource($source);
    echo $ok ? "ok\n" : "error\n";
} else {
    $r = $svc->runDue($force);
    echo "done: {$r['ok']} ok, {$r['errors']} errors\n";
}

Database::query("SELECT RELEASE_LOCK('daybreak_fetch')");
