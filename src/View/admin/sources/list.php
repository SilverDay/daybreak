<?php
declare(strict_types=1);
use Daybreak\Security\Html;
?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Sources</h1>
  <a href="/admin/sources/create" class="btn btn-primary">+ Add source</a>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Slug</th>
        <th>Category</th>
        <th>Adapter</th>
        <th>Status</th>
        <th class="num">Failures</th>
        <th>Last success</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($sources as $src): ?>
      <tr>
        <td><a href="/admin/sources/<?= (int) $src['id'] ?>"><?= Html::e($src['name']) ?></a></td>
        <td class="text-secondary text-sm"><?= Html::e($src['slug']) ?></td>
        <td><?= Html::e($src['category_name'] ?? '—') ?></td>
        <td class="text-sm"><code><?= Html::e($src['adapter_type']) ?></code></td>
        <td><span class="status-pill status-pill--<?= Html::e($src['status']) ?>"><?= Html::e($src['status']) ?></span></td>
        <td class="num <?= (int) $src['consecutive_failures'] > 0 ? 'text-danger' : '' ?>"><?= (int) $src['consecutive_failures'] ?></td>
        <td class="text-secondary text-sm"><?= Html::e($src['last_success_at'] ? (new DateTimeImmutable($src['last_success_at']))->format('M j, H:i') : '—') ?></td>
        <td><a href="/admin/sources/<?= (int) $src['id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($sources)): ?>
      <tr><td colspan="8" class="text-secondary text-center">No sources yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
