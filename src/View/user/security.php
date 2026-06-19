<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
?>
<div class="settings-page">

  <section class="settings-section">
    <h2 class="settings-section-title">Change password</h2>
    <form method="post" action="/settings/security">
      <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
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

</div>
