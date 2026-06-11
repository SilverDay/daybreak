<?php declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
$_old = ['email' => Html::e($_POST['email'] ?? ''), 'name' => Html::e($_POST['name'] ?? '')];
?>
<form method="post" action="/register" novalidate>
  <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">

  <div class="form-group">
    <label class="form-label" for="name">Display name</label>
    <input id="name" class="form-input" type="text" name="name"
           value="<?= $_old['name'] ?>"
           autocomplete="name" required maxlength="80">
  </div>

  <div class="form-group">
    <label class="form-label" for="email">Email address</label>
    <input id="email" class="form-input" type="email" name="email"
           value="<?= $_old['email'] ?>"
           autocomplete="email" required>
  </div>

  <div class="form-group">
    <label class="form-label" for="password">Password</label>
    <input id="password" class="form-input" type="password" name="password"
           autocomplete="new-password" required minlength="12">
    <p class="form-hint">Minimum 12 characters.</p>
  </div>

  <button type="submit" class="btn btn-primary btn-block">Create account</button>
</form>

<div class="auth-links">
  <a href="/login" class="form-link">Already have an account? Sign in</a>
</div>
