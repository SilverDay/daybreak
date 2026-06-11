<?php
declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
use Daybreak\Service\AuthService;

if (!function_exists('relativeTime')) {
    function relativeTime(?string $dateStr): string
    {
        if ($dateStr === null || $dateStr === '') {
            return '';
        }
        try {
            $dt   = new DateTimeImmutable($dateStr, new DateTimeZone('UTC'));
            $diff = time() - $dt->getTimestamp();
        } catch (\Throwable) {
            return '';
        }
        if ($diff < 0)      return 'just now';
        if ($diff < 60)     return 'just now';
        if ($diff < 3600)   return (int) ($diff / 60) . 'm ago';
        if ($diff < 86400)  return (int) ($diff / 3600) . 'h ago';
        if ($diff < 604800) return (int) ($diff / 86400) . 'd ago';
        return $dt->format('M j');
    }
}

$_flash    = $_SESSION['flash']       ?? null;
$_flashErr = $_SESSION['flash_error'] ?? null;
if ($_flash)    { unset($_SESSION['flash']); }
if ($_flashErr) { unset($_SESSION['flash_error']); }

$_cat     = $activeCategory ?? null;
// feedBase vars: controllers set these for personalised feed; public page leaves them unset.
$_base    = $allFeedUrl  ?? '/';
$_catBase = $catFeedBase ?? '/category/';
// windowMode can be 'since' (string) or an int; falls back to $windowDays for public page.
$_winMode = $windowMode  ?? (int) ($windowDays ?? 1);
$_winOpts = $windowOptions ?? [1 => 'Last 24h', 3 => 'Last 3 days', 7 => 'Last 7 days', 30 => 'Last 30 days'];
$_filterAction = $_cat !== null
    ? Html::e($_catBase . $_cat)
    : Html::e($_base);
$_currentUser = AuthService::currentUser();
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= Html::e(Csrf::token()) ?>">
  <title><?= Html::e($title ?? 'Latest') ?> · Daybreak</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body>

<header class="site-header">
  <div class="site-header-inner">
    <a href="/" class="logo">Daybreak</a>
    <span class="site-tagline">Security News</span>
    <nav class="site-nav" aria-label="User navigation">
      <?php if ($_currentUser): ?>
        <a href="/feed" class="site-nav-link<?= ($activeNav ?? '') === 'myfeed' ? ' site-nav-link--active' : '' ?>">My Feed</a>
        <a href="/settings/account" class="site-nav-link"><?= Html::e($_currentUser['display_name']) ?></a>
        <form method="post" action="/logout" class="site-nav-logout">
          <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
          <button type="submit" class="site-nav-btn">Sign out</button>
        </form>
      <?php else: ?>
        <a href="/login"    class="site-nav-link">Sign in</a>
        <a href="/register" class="site-nav-link site-nav-link--cta">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="filter-bar" role="navigation" aria-label="Filter articles">
  <div class="filter-bar-inner">
    <a href="<?= Html::e($_base) ?>?days=<?= Html::e((string) $_winMode) ?>"
       class="cat-chip<?= $_cat === null ? ' is-active' : '' ?>">All</a>
    <?php foreach ($categories ?? [] as $cat): ?>
    <a href="<?= Html::e($_catBase . $cat['slug']) ?>?days=<?= Html::e((string) $_winMode) ?>"
       class="cat-chip<?= $_cat === $cat['slug'] ? ' is-active' : '' ?>"><?= Html::e($cat['name']) ?></a>
    <?php endforeach; ?>
    <form class="window-form" method="get" action="<?= $_filterAction ?>">
      <label for="window-days" class="sr-only">Time window</label>
      <select id="window-days" name="days" class="window-select">
        <?php foreach ($_winOpts as $val => $label): ?>
        <option value="<?= Html::e((string) $val) ?>"<?= (string) $_winMode === (string) $val ? ' selected' : '' ?>><?= Html::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php if ($_flash || $_flashErr): ?>
<div class="flash-wrap">
  <?php if ($_flash): ?><div class="flash flash-success"><?= Html::e($_flash) ?></div><?php endif; ?>
  <?php if ($_flashErr): ?><div class="flash flash-error"><?= Html::e($_flashErr) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<main>
<div class="content-grid">
<section class="feed-column" aria-label="News articles">
