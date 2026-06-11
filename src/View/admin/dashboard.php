<?php
declare(strict_types=1);
use Daybreak\Security\Html;
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
    <div class="admin-stat-value"><?= (int) ($counts['disabled'] ?? 0) + (int) ($counts['auto_disabled'] ?? 0) ?></div>
    <div class="admin-stat-label">Disabled / degraded</div>
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
          <th>Last error</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sources as $src): ?>
        <tr>
          <td><a href="/admin/sources/<?= (int) $src['id'] ?>"><?= Html::e($src['name']) ?></a></td>
          <td><?= Html::e($src['category_name'] ?? '—') ?></td>
          <td><span class="status-pill status-pill--<?= Html::e($src['status']) ?>"><?= Html::e($src['status']) ?></span></td>
          <td class="num <?= (int) $src['consecutive_failures'] > 0 ? 'text-danger' : '' ?>"><?= (int) $src['consecutive_failures'] ?></td>
          <td class="num"><?= (int) $src['items_today'] ?></td>
          <td class="text-secondary"><?= Html::e($src['last_success_at'] ? (new DateTimeImmutable($src['last_success_at']))->format('M j, H:i') : '—') ?></td>
          <td class="text-danger text-sm text-truncate" title="<?= Html::e($src['last_error'] ?? '') ?>"><?= Html::e(mb_substr($src['last_error'] ?? '', 0, 60)) ?></td>
          <td><a href="/admin/sources/<?= (int) $src['id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
