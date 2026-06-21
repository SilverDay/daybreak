<?php

declare(strict_types=1);

use Daybreak\Security\Html;

$categories = $categories ?? [];
?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Categories</h1>
  <a href="/admin/categories/create" class="btn btn-primary">+ New category</a>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th data-sort="num">Order</th>
        <th data-sort="text" data-sort-dir="asc">Name</th>
        <th data-sort="text">Slug</th>
        <th>Color</th>
        <th class="num" data-sort="num">Sources</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr>
          <td class="num text-secondary"><?= (int) $cat['sort_order'] ?></td>
          <td>
            <a href="/admin/categories/<?= (int) $cat['id'] ?>">
              <span class="source-badge" data-badge-color="<?= Html::e($cat['color'] ?: '#64748b') ?>"><?= Html::e($cat['name']) ?></span>
            </a>
          </td>
          <td class="text-secondary text-sm"><?= Html::e($cat['slug']) ?></td>
          <td class="text-sm">
            <?php if (!empty($cat['color'])): ?>
              <code><?= Html::e($cat['color']) ?></code>
            <?php else: ?>
              <span class="text-secondary">—</span>
            <?php endif; ?>
          </td>
          <td class="num"><?= (int) $cat['source_count'] ?></td>
          <td><a href="/admin/categories/<?= (int) $cat['id'] ?>" class="btn btn-sm btn-secondary">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($categories)): ?>
        <tr>
          <td colspan="6" class="text-secondary text-center">No categories yet.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
