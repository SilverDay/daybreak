<?php declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
?>
<form method="post" action="/login" novalidate>
  <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">

  <div class="form-group">
    <label class="form-label" for="email">Email address</label>
    <input id="email" class="form-input" type="email" name="email"
           value="<?= Html::e($_POST['email'] ?? '') ?>"
           autocomplete="email" required>
  </div>

  <div class="form-group">
    <label class="form-label" for="password">Password</label>
    <input id="password" class="form-input" type="password" name="password"
           autocomplete="current-password" required>
  </div>

  <label class="remember-me-label">
    <input type="checkbox" name="remember_me" value="1">
    Stay logged in for 10 days
  </label>

  <button type="submit" class="btn btn-primary btn-block">Sign in</button>
</form>

<div class="auth-links">
  <a href="/password/forgot" class="form-link">Forgot your password?</a>
  <a href="/register" class="form-link">No account? Create one</a>
</div>
