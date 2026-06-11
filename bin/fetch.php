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
$lock = fopen(DB_ROOT . '/storage/fetch.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
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

flock($lock, LOCK_UN);
