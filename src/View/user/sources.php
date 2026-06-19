<?php
declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

// $grouped             — array<categoryName, source[]>
// $disabledIds         — array<sourceId => 0> (flipped for O(1) lookup)
// $availableLanguages  — string[] of ISO 639-1 codes present on active sources
// $preferredLanguages  — string[] of codes the user currently has selected
$grouped            = isset($grouped) && is_array($grouped) ? $grouped : [];
$disabledIds        = isset($disabledIds) && is_array($disabledIds) ? $disabledIds : [];
$availableLanguages = isset($availableLanguages) && is_array($availableLanguages) ? $availableLanguages : [];
$preferredLanguages = isset($preferredLanguages) && is_array($preferredLanguages) ? $preferredLanguages : [];
$preferredSet = array_flip($preferredLanguages);

$languageLabels = [
  'en' => 'English', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish',
  'pt' => 'Portuguese', 'nl' => 'Dutch', 'it' => 'Italian', 'ja' => 'Japanese',
  'zh' => 'Chinese', 'ko' => 'Korean', 'ru' => 'Russian', 'ar' => 'Arabic',
  'pl' => 'Polish', 'sv' => 'Swedish', 'fi' => 'Finnish', 'da' => 'Danish', 'no' => 'Norwegian',
];
?>
<div class="settings-page settings-page--wide">

  <?php if (!empty($availableLanguages)): ?>
  <section class="settings-section">
    <h2 class="settings-section-title">Language filter</h2>
    <p class="form-hint" style="margin-bottom:1.25rem">
      Restrict <a href="/feed" class="form-link">My Feed</a> to specific languages.
      Sources without a language tag are always shown. Leave everything unchecked to see all languages.
    </p>
    <form method="post" action="/settings/sources">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="languages">
      <fieldset class="sources-group">
        <legend class="sources-group-label">Show articles in</legend>
        <?php foreach ($availableLanguages as $code): ?>
          <label class="source-toggle">
            <input type="checkbox" name="preferred_languages[]"
                   value="<?= Html::e($code) ?>"
                   <?= isset($preferredSet[$code]) ? 'checked' : '' ?>>
            <span class="source-toggle-name"><?= Html::e($languageLabels[$code] ?? $code) ?></span>
            <span class="source-toggle-attr"><?= Html::e($code) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <div class="sources-actions">
        <button type="submit" class="btn btn-primary">Save language filter</button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <section class="settings-section">
    <h2 class="settings-section-title">Source preferences</h2>
    <p class="form-hint" style="margin-bottom:1.25rem">
      Choose which sources appear in <a href="/feed" class="form-link">My Feed</a>.
      Deselected sources still appear on the public page. All sources are included by default.
    </p>

    <form method="post" action="/settings/sources">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="sources">

      <?php foreach ($grouped as $catName => $catSources): ?>
      <fieldset class="sources-group">
        <legend class="sources-group-label"><?= Html::e($catName) ?></legend>
        <?php foreach ($catSources as $src): ?>
        <label class="source-toggle">
          <input type="checkbox" name="sources[]"
                 value="<?= Html::e((string) $src['id']) ?>"
                 <?= !isset($disabledIds[(int) $src['id']]) ? 'checked' : '' ?>>
          <span class="source-toggle-name"><?= Html::e($src['name']) ?></span>
          <span class="source-toggle-attr"><?= Html::e($src['attribution_text']) ?></span>
        </label>
        <?php endforeach; ?>
      </fieldset>
      <?php endforeach; ?>

      <?php if (empty($grouped)): ?>
      <p class="form-hint">No active sources yet — an admin will add them soon.</p>
      <?php endif; ?>

      <div class="sources-actions">
        <button type="submit" class="btn btn-primary">Save preferences</button>
        <button type="button" class="btn btn-secondary" id="select-all">Select all</button>
        <button type="button" class="btn btn-secondary" id="deselect-all">Deselect all</button>
      </div>
    </form>
  </section>

</div>
