<?php declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
?>
<div class="settings-page">

  <section class="settings-section">
    <h2 class="settings-section-title">Feed preferences</h2>
    <p class="form-hint" style="margin-bottom:1rem">
      Manage which sources appear in your feed:
      <a href="/settings/sources" class="form-link">Source preferences &rarr;</a>
    </p>
    <form method="post" action="/settings/account">
      <input type="hidden" name="_csrf"   value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action"  value="window">
      <div class="form-group">
        <label class="form-label" for="default_window_days">Default time window</label>
        <select id="default_window_days" name="default_window_days" class="form-input">
          <?php foreach ([1 => 'Last 24 hours', 3 => 'Last 3 days', 7 => 'Last 7 days', 30 => 'Last 30 days'] as $d => $label): ?>
          <option value="<?= $d ?>"<?= (int)($user['default_window_days'] ?? 1) === $d ? ' selected' : '' ?>><?= Html::e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="form-hint">Fallback when you switch to &ldquo;Last X days&rdquo; in My Feed.</p>
      </div>
      <button type="submit" class="btn btn-primary">Save preference</button>
    </form>
  </section>

  <section class="settings-section">
    <h2 class="settings-section-title">Display name</h2>
    <form method="post" action="/settings/account">
      <input type="hidden" name="_csrf"   value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action"  value="name">
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
    <h2 class="settings-section-title">Change password</h2>
    <form method="post" action="/settings/account">
      <input type="hidden" name="_csrf"  value="<?= Html::e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="password">
      <div class="form-group">
        <label class="form-label" for="current_password">Current password</label>
        <input id="current_password" class="form-input" type="password"
               name="current_password" autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="new_password">New password</label>
        <input id="new_password" class="form-input" type="password"
               name="new_password" autocomplete="new-password" required minlength="12">
        <p class="form-hint">Minimum 12 characters.</p>
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirm new password</label>
        <input id="confirm_password" class="form-input" type="password"
               name="confirm_password" autocomplete="new-password" required minlength="12">
      </div>
      <button type="submit" class="btn btn-primary">Change password</button>
    </form>
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
