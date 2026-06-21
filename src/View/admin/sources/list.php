<?php

declare(strict_types=1);

use Daybreak\Security\Html;

$sources = $sources ?? [];

$now = new DateTimeImmutable('now');
$relativeAge = static function (?string $raw) use ($now): string {
  if (!is_string($raw) || $raw === '') {
    return 'never';
  }

  try {
    $at = new DateTimeImmutable($raw);
  } catch (Throwable) {
    return 'unknown';
  }

  $delta = $now->getTimestamp() - $at->getTimestamp();
  if ($delta < 60) {
    return 'just now';
  }
  if ($delta < 3600) {
    return (string) floor($delta / 60) . 'm ago';
  }
  if ($delta < 86400) {
    return (string) floor($delta / 3600) . 'h ago';
  }

  return (string) floor($delta / 86400) . 'd ago';
};
?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Sources</h1>
  <div class="admin-page-header-actions">
    <a href="/admin/sources/import-opml" class="btn btn-secondary">Import OPML</a>
    <a href="/admin/sources/create" class="btn btn-primary">+ Add source</a>
  </div>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th data-sort="text" data-sort-dir="asc">Name</th>
        <th data-sort="text">Slug</th>
        <th data-sort="text">Category</th>
        <th data-sort="text">Adapter</th>
        <th data-sort="text">Status</th>
        <th class="num" data-sort="num">Failures</th>
        <th data-sort="date">Last success</th>
        <th>Last fetch health</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sources as $src): ?>
        <?php
        $failures = (int) ($src['consecutive_failures'] ?? 0);
        $riskClass = '';
        if ($failures >= 6) {
          $riskClass = 'health-risk--critical';
        } elseif ($failures >= 3) {
          $riskClass = 'health-risk--warn';
        }

        $okLast5 = (int) ($src['ok_last5'] ?? 0);
        $zeroLast5 = (int) ($src['zero_ok_last5'] ?? 0);
        $zeroTrend = $okLast5 >= 3 && $okLast5 === $zeroLast5;

        $lastSuccessRaw = is_string($src['last_success_at'] ?? null) ? $src['last_success_at'] : null;
        $lastSuccessRelative = $relativeAge($lastSuccessRaw);
        $isStale = $lastSuccessRelative === 'never' || str_contains($lastSuccessRelative, 'd ago');
        ?>
        <tr>
          <td><a href="/admin/sources/<?= (int) $src['id'] ?>"><?= Html::e($src['name']) ?></a></td>
          <td class="text-secondary text-sm"><?= Html::e($src['slug']) ?></td>
          <td><?= Html::e($src['category_name'] ?? '—') ?></td>
          <td class="text-sm"><code><?= Html::e($src['adapter_type']) ?></code></td>
          <td><span class="status-pill status-pill--<?= Html::e($src['status']) ?>"><?= Html::e($src['status']) ?></span></td>
          <td class="num <?= $failures > 0 ? 'text-danger ' : '' ?><?= $riskClass ?>"><?= $failures ?></td>
          <td class="<?= $isStale ? 'text-danger' : 'text-secondary' ?> text-sm"
              data-sort-value="<?= Html::e($lastSuccessRaw ?? '') ?>">
            <strong><?= Html::e($lastSuccessRelative) ?></strong>
            <div class="text-secondary"><?= Html::e($src['last_success_at'] ? (new DateTimeImmutable($src['last_success_at']))->format('M j, H:i') : '—') ?></div>
          </td>
          <td class="text-sm">
            <div>HTTP <?= Html::e($src['last_http_status'] !== null ? (string) (int) $src['last_http_status'] : '—') ?></div>
            <div class="text-secondary">avg <?= Html::e($src['avg_duration_ms'] !== null ? (string) (int) $src['avg_duration_ms'] . ' ms' : '—') ?></div>
            <?php if ($zeroTrend): ?>
              <div class="health-flag health-flag--warn">zero-yield trend</div>
            <?php endif; ?>
          </td>
          <td><a href="/admin/sources/<?= (int) $src['id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($sources)): ?>
        <tr>
          <td colspan="9" class="text-secondary text-center">No sources yet.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
