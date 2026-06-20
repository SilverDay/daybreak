<?php

declare(strict_types=1);

use Daybreak\Security\Html;

$counts = $counts ?? [];
$pendingSuggestions = $pendingSuggestions ?? 0;
$totalArticles = $totalArticles ?? 0;
$sources = $sources ?? [];

$topArticles = $topArticles ?? [];
$topSources  = $topSources  ?? [];
$healthSummary = $healthSummary ?? [
  'degraded' => 0,
  'auto_disabled' => 0,
  'zero_yield_trend' => 0,
  'stale_24h' => 0,
];

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

$latestFetchAt = null;
foreach ($sources as $src) {
  $raw = $src['last_fetch_at'] ?? null;
  if (!is_string($raw) || $raw === '') {
    continue;
  }

  try {
    $candidate = new DateTimeImmutable($raw);
  } catch (Throwable) {
    continue;
  }

  if ($latestFetchAt === null || $candidate > $latestFetchAt) {
    $latestFetchAt = $candidate;
  }
}
?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Dashboard</h1>
</div>

<div class="admin-stat-grid">
  <div class="admin-stat-card">
    <div class="admin-stat-value"><?= (int) ($counts['active'] ?? 0) ?></div>
    <div class="admin-stat-label">Active sources</div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-value <?= (int) ($healthSummary['degraded'] ?? 0) > 0 ? 'admin-stat-value--warn' : '' ?>"><?= (int) ($healthSummary['degraded'] ?? 0) ?></div>
    <div class="admin-stat-label">Degraded sources</div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-value <?= (int) ($healthSummary['auto_disabled'] ?? 0) > 0 ? 'admin-stat-value--danger' : '' ?>"><?= (int) ($healthSummary['auto_disabled'] ?? 0) ?></div>
    <div class="admin-stat-label">Auto-disabled</div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-value <?= (int) ($healthSummary['zero_yield_trend'] ?? 0) > 0 ? 'admin-stat-value--warn' : '' ?>"><?= (int) ($healthSummary['zero_yield_trend'] ?? 0) ?></div>
    <div class="admin-stat-label">Zero-yield trends</div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-value <?= $pendingSuggestions > 0 ? 'admin-stat-value--warn' : '' ?>"><?= (int) $pendingSuggestions ?></div>
    <div class="admin-stat-label">Pending suggestions</div>
  </div>
  <div class="admin-stat-card">
    <div class="admin-stat-value"><?= number_format($totalArticles) ?></div>
    <div class="admin-stat-label">Total articles</div>
  </div>
</div>

<div class="admin-health-summary">
  <div class="admin-health-summary-item">
    <span class="admin-health-summary-label">Stale over 24h</span>
    <strong class="admin-health-summary-value <?= (int) ($healthSummary['stale_24h'] ?? 0) > 0 ? 'text-danger' : '' ?>"><?= (int) ($healthSummary['stale_24h'] ?? 0) ?></strong>
  </div>
  <div class="admin-health-summary-item">
    <span class="admin-health-summary-label">Failure threshold</span>
    <strong class="admin-health-summary-value">degraded at 3, auto-disabled at 8</strong>
  </div>
  <div class="admin-health-summary-item">
    <span class="admin-health-summary-label">Last fetch at</span>
    <strong class="admin-health-summary-value">
      <?php if ($latestFetchAt !== null): ?>
        <?= Html::e($latestFetchAt->format('M j, H:i')) ?>
      <?php else: ?>
        —
      <?php endif; ?>
    </strong>
  </div>
</div>

<div class="admin-section">
  <div class="admin-section-header">
    <h2 class="admin-section-title">Source health</h2>
    <a href="/admin/sources/create" class="btn btn-primary btn-sm">+ Add source</a>
  </div>

  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Category</th>
          <th>Status</th>
          <th class="num">Failures</th>
          <th class="num">Items today</th>
          <th>Last success</th>
          <th>Last fetch health</th>
          <th>Last error</th>
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

          $lastSuccess = $src['last_success_at'] ?? null;
          $lastSuccessRelative = $relativeAge(is_string($lastSuccess) ? $lastSuccess : null);
          $isStale = $lastSuccessRelative === 'never' || str_contains($lastSuccessRelative, 'd ago');
          ?>
          <tr>
            <td><a href="/admin/sources/<?= (int) $src['id'] ?>"><?= Html::e($src['name']) ?></a></td>
            <td><?= Html::e($src['category_name'] ?? '—') ?></td>
            <td><span class="status-pill status-pill--<?= Html::e($src['status']) ?>"><?= Html::e($src['status']) ?></span></td>
            <td class="num <?= $failures > 0 ? 'text-danger ' : '' ?><?= $riskClass ?>"><?= $failures ?></td>
            <td class="num"><?= (int) $src['items_today'] ?></td>
            <td class="<?= $isStale ? 'text-danger' : 'text-secondary' ?>">
              <strong><?= Html::e($lastSuccessRelative) ?></strong>
              <div class="text-sm text-secondary"><?= Html::e($src['last_success_at'] ? (new DateTimeImmutable($src['last_success_at']))->format('M j, H:i') : '—') ?></div>
            </td>
            <td>
              <div class="text-sm">HTTP <?= Html::e($src['last_http_status'] !== null ? (string) (int) $src['last_http_status'] : '—') ?></div>
              <div class="text-sm text-secondary">avg <?= Html::e($src['avg_duration_ms'] !== null ? (string) (int) $src['avg_duration_ms'] . ' ms' : '—') ?></div>
              <?php if ($zeroTrend): ?>
                <div class="health-flag health-flag--warn">zero-yield trend</div>
              <?php endif; ?>
            </td>
            <td class="text-danger text-sm text-truncate" title="<?= Html::e($src['last_error'] ?? '') ?>"><?= Html::e(mb_substr($src['last_error'] ?? '', 0, 60)) ?></td>
            <td><a href="/admin/sources/<?= (int) $src['id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-section">
  <div class="admin-section-header">
    <h2 class="admin-section-title">Reading stats</h2>
  </div>

  <?php if ($topArticles === [] && $topSources === []): ?>
    <p class="text-secondary text-sm">No read events recorded yet.</p>
  <?php else: ?>
  <div class="admin-read-stats-grid">

    <div>
      <p class="admin-subsection-title">Top articles &mdash; last 30 days</p>
      <?php if ($topArticles === []): ?>
        <p class="text-secondary text-sm">No data yet.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table admin-table--sm">
          <thead>
            <tr>
              <th>Article</th>
              <th>Source</th>
              <th class="num">Reads</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topArticles as $row): ?>
              <tr>
                <td class="text-truncate read-stats-article-col">
                  <a href="<?= Html::e($row['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">
                    <?= Html::e(mb_substr($row['title'], 0, 80)) ?>
                  </a>
                </td>
                <td class="text-secondary"><?= Html::e($row['source_name']) ?></td>
                <td class="num"><?= (int) $row['reads'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <p class="admin-subsection-title">Top sources &mdash; all time</p>
      <?php if ($topSources === []): ?>
        <p class="text-secondary text-sm">No data yet.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table class="admin-table admin-table--sm">
          <thead>
            <tr>
              <th>Source</th>
              <th class="num">Reads</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topSources as $row): ?>
              <tr>
                <td><?= Html::e($row['name']) ?></td>
                <td class="num"><?= (int) $row['reads'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
  <?php endif; ?>
</div>
