<?php declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
?>
<form method="post" action="/password/reset/<?= Html::e($token ?? '') ?>" novalidate>
  <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">

  <div class="form-group">
    <label class="form-label" for="password">New password</label>
    <input id="password" class="form-input" type="password" name="password"
           autocomplete="new-password" required minlength="12">
    <p class="form-hint">Minimum 12 characters.</p>
  </div>

  <div class="form-group">
    <label class="form-label" for="confirm">Confirm new password</label>
    <input id="confirm" class="form-input" type="password" name="confirm"
           autocomplete="new-password" required minlength="12">
  </div>

  <button type="submit" class="btn btn-primary btn-block">Set new password</button>
</form>
