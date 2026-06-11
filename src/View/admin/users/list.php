<?php
declare(strict_types=1);
use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
use Daybreak\Service\AuthService;

$_me = AuthService::currentUser();
?>
<div class="admin-page-header">
  <h1 class="admin-page-title">Users</h1>
</div>

<div class="table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Display name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Last login</th>
        <th>Joined</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= Html::e($u['display_name']) ?><?= (int) $u['id'] === (int) $_me['id'] ? ' <em class="text-secondary text-sm">(you)</em>' : '' ?></td>
        <td class="text-sm"><?= Html::e($u['email']) ?></td>
        <td><span class="status-pill status-pill--<?= $u['role'] === 'admin' ? 'active' : 'pending' ?>"><?= Html::e($u['role']) ?></span></td>
        <td><span class="status-pill status-pill--<?= $u['status'] === 'active' ? 'active' : 'disabled' ?>"><?= Html::e($u['status']) ?></span></td>
        <td class="text-secondary text-sm"><?= Html::e($u['last_login_at'] ? (new DateTimeImmutable($u['last_login_at']))->format('M j, Y') : '—') ?></td>
        <td class="text-secondary text-sm"><?= Html::e((new DateTimeImmutable($u['created_at']))->format('M j, Y')) ?></td>
        <td>
          <?php if ((int) $u['id'] !== (int) $_me['id']): ?>
          <div class="admin-action-row admin-action-row--inline">
            <?php if ($u['status'] === 'active'): ?>
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="disable">
              <button type="submit" class="btn btn-sm btn-secondary">Disable</button>
            </form>
            <?php else: ?>
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="enable">
              <button type="submit" class="btn btn-sm btn-primary">Enable</button>
            </form>
            <?php endif; ?>
            <?php if ($u['role'] === 'user'): ?>
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="promote">
              <button type="submit" class="btn btn-sm btn-secondary">Make admin</button>
            </form>
            <?php else: ?>
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>">
              <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="demote">
              <button type="submit" class="btn btn-sm btn-secondary">Remove admin</button>
            </form>
            <?php endif; ?>
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>"
                  onsubmit="return confirm('Permanently delete account for <?= Html::e(addslashes($u['email'])) ?>?')">
              <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
