<?php

declare(strict_types=1);

use Daybreak\Security\Csrf;
use Daybreak\Security\Html;

$groupedSources = isset($groupedSources) && is_array($groupedSources) ? $groupedSources : [];
$selectedSlots  = isset($selectedSlots) && is_array($selectedSlots) ? $selectedSlots : [1 => null, 2 => null];

$slot1Selected = isset($selectedSlots[1]) ? (int) $selectedSlots[1] : 0;
$slot2Selected = isset($selectedSlots[2]) ? (int) $selectedSlots[2] : 0;
?>
<div class="settings-page settings-page--wide">
  <section class="settings-section">
    <h2 class="settings-section-title">Widget sources</h2>
    <p class="form-hint u-mb-1">
      Assign source widgets for the two right-rail slots in <a href="/feed" class="form-link">My Feed</a>.
      Leave a slot on <strong>Default</strong> to keep the standard widget content.
    </p>

    <form method="post" action="/settings/widgets">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">

      <fieldset class="sources-group">
        <legend class="sources-group-label">Widget slot 1</legend>
        <label class="form-label" for="slot-1-source">Source</label>
        <select id="slot-1-source" name="slot_1_source_id" class="form-input">
          <option value="">Default (Ransomware Activity)</option>
          <?php foreach ($groupedSources as $category => $sources): ?>
            <optgroup label="<?= Html::e((string) $category) ?>">
              <?php foreach ($sources as $source): ?>
                <?php $sourceId = (int) ($source['id'] ?? 0); ?>
                <option value="<?= Html::e((string) $sourceId) ?>" <?= $slot1Selected === $sourceId ? 'selected' : '' ?>>
                  <?= Html::e((string) ($source['name'] ?? 'Unknown source')) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <fieldset class="sources-group">
        <legend class="sources-group-label">Widget slot 2</legend>
        <label class="form-label" for="slot-2-source">Source</label>
        <select id="slot-2-source" name="slot_2_source_id" class="form-input">
          <option value="">Default (Recent CVEs)</option>
          <?php foreach ($groupedSources as $category => $sources): ?>
            <optgroup label="<?= Html::e((string) $category) ?>">
              <?php foreach ($sources as $source): ?>
                <?php $sourceId = (int) ($source['id'] ?? 0); ?>
                <option value="<?= Html::e((string) $sourceId) ?>" <?= $slot2Selected === $sourceId ? 'selected' : '' ?>>
                  <?= Html::e((string) ($source['name'] ?? 'Unknown source')) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </fieldset>

      <div class="sources-actions">
        <button type="submit" class="btn btn-primary">Save widget preferences</button>
      </div>
    </form>
  </section>
</div>
