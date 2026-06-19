<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

/** @var bool $hasKiojuKey */
?>
<div class="settings-page">

  <section class="settings-section">
    <h2 class="settings-section-title">Theme</h2>
    <div class="theme-radio-group" role="radiogroup" aria-label="Colour theme">
      <label class="theme-radio"><input type="radio" name="theme" value="light"> Light</label>
      <label class="theme-radio"><input type="radio" name="theme" value="dark"> Dark</label>
      <label class="theme-radio"><input type="radio" name="theme" value="system"> System default</label>
    </div>
  </section>

  <section class="settings-section">
    <h2 class="settings-section-title">Display name</h2>
    <form method="post" action="/settings/account">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="name">
      <div class="form-group">
        <label class="form-label" for="display_name">Display name</label>
        <input id="display_name" class="form-input" type="text" name="display_name"
          value="<?= Html::e($user['display_name'] ?? '') ?>"
          maxlength="80" required>
      </div>
      <button type="submit" class="btn btn-primary">Update name</button>
    </form>
  </section>

  <section class="settings-section">
    <h2 class="settings-section-title">Feed preferences</h2>
    <form method="post" action="/settings/account">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="window">
      <div class="form-group">
        <label class="form-label" for="default_window_days">Default time window</label>
        <select id="default_window_days" name="default_window_days" class="form-input">
          <?php foreach ([1 => 'Last 24 hours', 3 => 'Last 3 days', 7 => 'Last 7 days', 30 => 'Last 30 days'] as $d => $label): ?>
            <option value="<?= $d ?>" <?= (int)($user['default_window_days'] ?? 1) === $d ? ' selected' : '' ?>><?= Html::e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="form-hint">Fallback when you switch to &ldquo;Last X days&rdquo; in My Feed.</p>
      </div>
      <button type="submit" class="btn btn-primary">Save preference</button>
    </form>
  </section>

  <section class="settings-section">
    <h2 class="settings-section-title">Kioju bookmarks</h2>
    <p class="form-hint" style="margin-bottom:1rem">
      Connect your Kioju API key to save article links directly from Daybreak.
    </p>
    <?php if ($hasKiojuKey): ?>
      <p class="form-hint" style="margin-bottom:1rem">Status: <strong>Connected</strong></p>
    <?php else: ?>
      <p class="form-hint" style="margin-bottom:1rem">Status: Not connected</p>
    <?php endif; ?>

    <form method="post" action="/settings/account" style="margin-bottom:0.75rem">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="kioju_save">
      <div class="form-group">
        <label class="form-label" for="kioju_api_key">Kioju API key</label>
        <input id="kioju_api_key" class="form-input" type="password" name="kioju_api_key"
          autocomplete="off" placeholder="Paste your API key">
        <p class="form-hint">Stored encrypted. Current key is never shown.</p>
      </div>
      <button type="submit" class="btn btn-primary">Save API key</button>
    </form>

    <?php if ($hasKiojuKey): ?>
      <form method="post" action="/settings/account">
        <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="kioju_remove">
        <button type="submit" class="btn btn-secondary">Remove API key</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="settings-section">
    <h2 class="settings-section-title">Data export</h2>
    <p class="form-hint">Download a copy of your personal data (DSGVO Art. 20).</p>
    <a href="/settings/export" class="btn btn-primary">Download data</a>
  </section>

  <section class="settings-section settings-section-danger">
    <h2 class="settings-section-title">Delete account</h2>
    <p class="form-hint">
      Permanently delete your account and all associated data. This cannot be undone.
    </p>
    <form method="post" action="/settings/account/delete"
      data-confirm="Delete your account permanently? This cannot be undone.">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
      <div class="form-group">
        <label class="form-label" for="confirm_delete">
          Type <strong>DELETE</strong> to confirm
        </label>
        <input id="confirm_delete" class="form-input" type="text" name="confirm"
          autocomplete="off" required placeholder="DELETE">
      </div>
      <button type="submit" class="btn btn-danger">Delete my account</button>
    </form>
  </section>

</div>
