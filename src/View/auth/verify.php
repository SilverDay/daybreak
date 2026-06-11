<?php declare(strict_types=1); ?>
<?php if ($verified ?? false): ?>
<p>Your email address has been verified. You can now sign in.</p>
<div class="auth-links">
  <a href="/login" class="btn btn-primary">Sign in</a>
</div>
<?php else: ?>
<p>This verification link is invalid or has expired.</p>
<p class="form-hint">
  If your link expired, please
  <a href="/register" class="form-link">register again</a>
  to receive a new verification email.
</p>
<?php endif; ?>
