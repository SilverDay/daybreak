<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

/** @var array $watchTerms  rows of {id, term} */
$watchTerms = $watchTerms ?? [];
?>
<div class="settings-page">

  <section class="settings-section">
    <h2 class="settings-section-title">Watch terms</h2>
    <p class="form-hint u-mb-1">
      Articles containing these keywords (case-insensitive) are highlighted in My Feed.
      Matches from the last 24&nbsp;hours also appear in an Alerts block at the top of your feed.
    </p>

    <?php if ($watchTerms !== []): ?>
      <ul class="watch-term-list">
        <?php foreach ($watchTerms as $t): ?>
          <li class="watch-term-item">
            <span><?= Html::e($t['term']) ?></span>
            <form method="post" action="/settings/watch">
              <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="term_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm">Remove</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="form-hint u-mb-1">No watch terms set.</p>
    <?php endif; ?>

    <form method="post" action="/settings/watch">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-group">
        <label class="form-label" for="new_term">Add a term</label>
        <input id="new_term" class="form-input" type="text" name="term"
          maxlength="120" required autocomplete="off" placeholder="e.g. Cisco, zero-day, ransomware">
        <p class="form-hint">Substring match &mdash; short terms match broadly.</p>
      </div>
      <button type="submit" class="btn btn-primary">Add term</button>
    </form>
  </section>

</div>
