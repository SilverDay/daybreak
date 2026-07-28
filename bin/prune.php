#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Daybreak maintenance: prune old articles, rotate fetch_log and login_attempts.
 * Intended to run daily via cron, e.g.:
 *   0 3 * * * php /srv/vhosts/daybreak.silverday.de/bin/prune.php
 *
 * Environment variables (or .env):
 *   PRUNE_ARTICLES_DAYS   — articles older than N days are deleted (default: 90)
 *   PRUNE_FETCHLOG_DAYS   — fetch_log rows older than N days (default: 30)
 *   PRUNE_ATTEMPTS_DAYS   — login_attempts rows older than N days (default: 30)
 *   PRUNE_AUDIT_DAYS      — audit_log rows older than N days (default: 365)
 *   PRUNE_WEBHOOK_DAYS    — webhook_log rows older than N days (default: 90)
 */

define('DB_ROOT', dirname(__DIR__));
require DB_ROOT . '/src/bootstrap.php';

use Daybreak\Config;
use Daybreak\Database;

$articleDays  = max(1, (int) Config::get('PRUNE_ARTICLES_DAYS',  '90'));
$fetchLogDays = max(1, (int) Config::get('PRUNE_FETCHLOG_DAYS',  '30'));
$attemptDays  = max(1, (int) Config::get('PRUNE_ATTEMPTS_DAYS',  '30'));
$auditDays    = max(1, (int) Config::get('PRUNE_AUDIT_DAYS',     '365'));
$webhookDays  = max(1, (int) Config::get('PRUNE_WEBHOOK_DAYS',   '90'));

function pruneTable(string $table, string $col, int $days): int
{
    $stmt = Database::query(
        "DELETE FROM {$table} WHERE {$col} < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT 5000",
        [$days]
    );
    return $stmt->rowCount();
}

$start = microtime(true);

$rows = [
    'articles'       => pruneTable('articles',       'published_at', $articleDays),
    'fetch_log'      => pruneTable('fetch_log',       'created_at',   $fetchLogDays),
    'login_attempts' => pruneTable('login_attempts',  'created_at',   $attemptDays),
    'sessions'       => pruneTable('sessions',        'last_activity', $attemptDays),
    'audit_log'      => pruneTable('audit_log',       'created_at',   $auditDays),
    'webhook_log'    => pruneTable('webhook_log',     'created_at',   $webhookDays),
];

// Prune stale pending users with expired verification tokens.
// A pending account where the email_verify token has expired is dead weight.
$stale = Database::query(
    "DELETE u FROM users u
     LEFT JOIN auth_tokens t ON t.user_id = u.id AND t.type = 'email_verify' AND t.expires_at > NOW()
     WHERE u.status = 'pending' AND t.id IS NULL
     LIMIT 500"
)->rowCount();
$rows['stale_pending_users'] = $stale;

$elapsed = round((microtime(true) - $start) * 1000);

foreach ($rows as $table => $n) {
    if ($n > 0) {
        echo "[prune] {$table}: {$n} rows removed\n";
    }
}
echo "[prune] done in {$elapsed}ms\n";
