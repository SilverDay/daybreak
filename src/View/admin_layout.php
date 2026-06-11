<?php
declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
use Daybreak\Service\AuthService;

$_flash    = $_SESSION['flash']       ?? null;
$_flashErr = $_SESSION['flash_error'] ?? null;
if ($_flash)    { unset($_SESSION['flash']); }
if ($_flashErr) { unset($_SESSION['flash_error']); }

$_currentUser = AuthService::currentUser();
$_adminNav    = $adminNav ?? '';
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= Html::e(Csrf::token()) ?>">
  <title><?= Html::e($title ?? 'Admin') ?> · Daybreak</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body class="admin-body">

<header class="site-header">
  <div class="site-header-inner">
    <a href="/" class="logo">Daybreak</a>
    <span class="site-tagline">Admin Panel</span>
    <nav class="site-nav" aria-label="User navigation">
      <a href="/" class="site-nav-link">View Site</a>
      <a href="/settings/account" class="site-nav-link"><?= Html::e($_currentUser['display_name'] ?? '') ?></a>
      <form method="post" action="/logout" class="site-nav-logout">
        <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
        <button type="submit" class="site-nav-btn">Sign out</button>
      </form>
    </nav>
  </div>
</header>

<div class="admin-subnav">
  <div class="admin-subnav-inner">
    <a href="/admin"             class="admin-nav-link<?= $_adminNav === 'dashboard'   ? ' is-active' : '' ?>">Dashboard</a>
    <a href="/admin/sources"     class="admin-nav-link<?= $_adminNav === 'sources'     ? ' is-active' : '' ?>">Sources</a>
    <a href="/admin/suggestions" class="admin-nav-link<?= $_adminNav === 'suggestions' ? ' is-active' : '' ?>">Suggestions</a>
    <a href="/admin/users"       class="admin-nav-link<?= $_adminNav === 'users'       ? ' is-active' : '' ?>">Users</a>
    <a href="/admin/audit"       class="admin-nav-link<?= $_adminNav === 'audit'       ? ' is-active' : '' ?>">Audit Log</a>
  </div>
</div>

<?php if ($_flash || $_flashErr): ?>
<div class="flash-wrap">
  <?php if ($_flash): ?><div class="flash flash-success"><?= Html::e($_flash) ?></div><?php endif; ?>
  <?php if ($_flashErr): ?><div class="flash flash-error"><?= Html::e($_flashErr) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<main class="admin-main">
<div class="admin-content">
