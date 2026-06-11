<?php
declare(strict_types=1);
use Daybreak\Security\Html;
?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Audit log</h1>
  <span class="text-secondary text-sm">Last 200 entries</span>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Time</th>
        <th>Actor</th>
        <th>Action</th>
        <th>Target type</th>
        <th>Target</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($entries as $e): ?>
      <tr>
        <td class="text-sm text-secondary"><?= Html::e((new DateTimeImmutable($e['created_at']))->format('M j, Y H:i')) ?></td>
        <td><?= Html::e($e['actor'] ?? 'system') ?></td>
        <td><code class="audit-action"><?= Html::e($e['action']) ?></code></td>
        <td class="text-secondary text-sm"><?= Html::e($e['target_type'] ?? '') ?></td>
        <td class="text-secondary text-sm"><?= Html::e($e['target_id'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($entries)): ?>
      <tr><td colspan="5" class="text-secondary text-center">No audit entries yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
