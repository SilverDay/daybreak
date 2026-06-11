<?php
declare(strict_types=1);

/** Apply pending migrations/*.sql in filename order, tracked in schema_migrations. */

require __DIR__ . '/../src/bootstrap.php';

use Daybreak\Database;

$dir   = __DIR__;
$files = glob($dir . '/*.sql') ?: [];
sort($files);

// Ensure tracking table exists (001 also creates it; this makes run.php idempotent).
Database::query('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(255) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)');

$applied = array_column(Database::query('SELECT filename FROM schema_migrations')->fetchAll(), 'filename');

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "skip  {$name}\n";
        continue;
    }
    echo "apply {$name} ... ";
    $sql = file_get_contents($file);
    Database::pdo()->exec($sql);
    Database::query('INSERT INTO schema_migrations (filename) VALUES (?)', [$name]);
    echo "ok\n";
}
echo "migrations complete\n";
