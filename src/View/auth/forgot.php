<?php declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
?>
<p class="form-hint u-mb-1">
  Enter your email address and we'll send you a link to reset your password.
</p>

<form method="post" action="/password/forgot" novalidate>
  <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">

  <div class="form-group">
    <label class="form-label" for="email">Email address</label>
    <input id="email" class="form-input" type="email" name="email"
           autocomplete="email" required>
  </div>

  <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
</form>

<div class="auth-links">
  <a href="/login" class="form-link">Back to sign in</a>
</div>
