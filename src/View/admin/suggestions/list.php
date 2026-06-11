<?php
declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

$pending  = array_filter($suggestions, fn($s) => $s['status'] === 'pending');
$reviewed = array_filter($suggestions, fn($s) => $s['status'] !== 'pending');
?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Suggestions
    <?php if (count($pending) > 0): ?>
    <span class="badge badge--warn"><?= count($pending) ?></span>
    <?php endif; ?>
  </h1>
</div>

<?php if (empty($pending) && empty($reviewed)): ?>
<p class="text-secondary">No suggestions yet.</p>
<?php endif; ?>

<?php if (!empty($pending)): ?>
<div class="admin-section">
  <h2 class="admin-section-title">Pending review</h2>
  <?php foreach ($pending as $sg): ?>
  <div class="suggestion-card">
    <div class="suggestion-header">
      <strong><?= Html::e($sg['name']) ?></strong>
      <span class="text-secondary text-sm"><?= Html::e($sg['suggester_name'] ?? 'anonymous') ?> · <?= Html::e((new DateTimeImmutable($sg['created_at']))->format('M j, Y')) ?></span>
    </div>
    <div class="suggestion-urls">
      <a href="<?= Html::e($sg['homepage_url']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="text-link"><?= Html::e($sg['homepage_url']) ?></a>
      <?php if ($sg['feed_url']): ?>
      <span class="text-secondary">·</span>
      <a href="<?= Html::e($sg['feed_url']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="text-link text-sm"><?= Html::e($sg['feed_url']) ?></a>
      <?php endif; ?>
      <?php if ($sg['detected_adapter']): ?>
      <span class="status-pill status-pill--active"><?= Html::e($sg['detected_adapter']) ?> detected</span>
      <?php endif; ?>
    </div>
    <?php if ($sg['note']): ?>
    <p class="suggestion-note"><?= Html::e($sg['note']) ?></p>
    <?php endif; ?>
    <div class="suggestion-actions">
      <form method="post" action="/admin/suggestions/<?= (int) $sg['id'] ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="approve">
        <label class="form-label" for="cat-<?= (int) $sg['id'] ?>">Category</label>
        <select id="cat-<?= (int) $sg['id'] ?>" name="category_id" class="form-input form-input--inline">
          <option value="">— none —</option>
          <?php foreach ($categories as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>"><?= Html::e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Approve → create source</button>
      </form>
      <form method="post" action="/admin/suggestions/<?= (int) $sg['id'] ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="reject">
        <input type="text" name="review_note" class="form-input form-input--inline" placeholder="Reason (optional)" maxlength="500">
        <button type="submit" class="btn btn-secondary btn-sm">Reject</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($reviewed)): ?>
<div class="admin-section">
  <h2 class="admin-section-title">Reviewed</h2>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Name</th><th>URL</th><th>Status</th><th>Reviewer</th><th>Note</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php foreach ($reviewed as $sg): ?>
        <tr>
          <td><?= Html::e($sg['name']) ?></td>
          <td class="text-sm"><a href="<?= Html::e($sg['homepage_url']) ?>" target="_blank" rel="noopener noreferrer nofollow" class="text-link"><?= Html::e($sg['homepage_url']) ?></a></td>
          <td><span class="status-pill status-pill--<?= $sg['status'] === 'approved' ? 'active' : 'disabled' ?>"><?= Html::e($sg['status']) ?></span></td>
          <td><?= Html::e($sg['reviewer_name'] ?? '—') ?></td>
          <td class="text-sm text-secondary"><?= Html::e($sg['review_note'] ?? '') ?></td>
          <td class="text-sm text-secondary"><?= Html::e($sg['reviewed_at'] ? (new DateTimeImmutable($sg['reviewed_at']))->format('M j') : '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
