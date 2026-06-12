<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
use Daybreak\Service\AuthService;
use Daybreak\Config;

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
if ($_flash) {
  unset($_SESSION['flash']);
}
if ($_flashErr) {
  unset($_SESSION['flash_error']);
}

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
$_showWidgets = (bool) ($showWidgets ?? false);
$_showFilterBar = (bool) ($showFilterBar ?? true);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$canonicalPath = (string) ($canonicalPath ?? $requestPath);
$scheme = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https' : 'http';
$configuredBaseUrl = '';
$configuredRaw = trim((string) (Config::get('APP_URL', '') ?? ''));
if ($configuredRaw !== '') {
  $parsedConfigured = parse_url($configuredRaw);
  if (
    is_array($parsedConfigured)
    && in_array((string) ($parsedConfigured['scheme'] ?? ''), ['http', 'https'], true)
    && isset($parsedConfigured['host'])
    && is_string($parsedConfigured['host'])
    && preg_match('/\A[a-z0-9.-]+\z/i', $parsedConfigured['host']) === 1
  ) {
    $configuredBaseUrl = (string) $parsedConfigured['scheme'] . '://' . (string) $parsedConfigured['host'];
    if (isset($parsedConfigured['port'])) {
      $configuredBaseUrl .= ':' . (int) $parsedConfigured['port'];
    }
  }
}

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$isValidHost = preg_match('/\A[a-z0-9.-]+(?::\d{1,5})?\z/i', $host) === 1;
$siteBaseUrl = $configuredBaseUrl !== ''
  ? $configuredBaseUrl
  : (($isValidHost) ? ($scheme . '://' . $host) : '');
$canonicalUrl = $siteBaseUrl !== '' ? $siteBaseUrl . $canonicalPath : $canonicalPath;
$socialImagePath = '/assets/images/daybreak-logo.png';
$socialImageUrl = $siteBaseUrl !== '' ? $siteBaseUrl . $socialImagePath : $socialImagePath;

$defaultRobots = 'index,follow';
$noIndexPrefixes = ['/feed', '/settings', '/search', '/suggest'];
foreach ($noIndexPrefixes as $prefix) {
  if ($requestPath === $prefix || str_starts_with($requestPath, $prefix . '/')) {
    $defaultRobots = 'noindex,nofollow,noarchive';
    break;
  }
}

$seoTitle = (string) ($seoTitle ?? (($title ?? 'Latest') . ' · Daybreak'));
$seoDescription = (string) ($seoDescription ?? 'Daybreak aggregates trusted security news, ransomware activity, and CVE updates in one view.');
$seoRobots = (string) ($seoRobots ?? $defaultRobots);
$ogType = (string) ($ogType ?? 'website');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= Html::e(Csrf::token()) ?>">
  <meta name="description" content="<?= Html::e($seoDescription) ?>">
  <meta name="robots" content="<?= Html::e($seoRobots) ?>">
  <link rel="canonical" href="<?= Html::e($canonicalUrl) ?>">

  <meta property="og:title" content="<?= Html::e($seoTitle) ?>">
  <meta property="og:description" content="<?= Html::e($seoDescription) ?>">
  <meta property="og:type" content="<?= Html::e($ogType) ?>">
  <meta property="og:url" content="<?= Html::e($canonicalUrl) ?>">
  <meta property="og:site_name" content="Daybreak">
  <meta property="og:image" content="<?= Html::e($socialImageUrl) ?>">
  <meta property="og:image:width" content="1434">
  <meta property="og:image:height" content="478">
  <meta property="og:image:alt" content="Daybreak logo">

  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= Html::e($seoTitle) ?>">
  <meta name="twitter:description" content="<?= Html::e($seoDescription) ?>">
  <meta name="twitter:image" content="<?= Html::e($socialImageUrl) ?>">
  <title><?= Html::e($title ?? 'Latest') ?> · Daybreak</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>

<body>

  <header class="site-header">
    <div class="site-header-inner">
      <a href="/" class="logo" aria-label="Daybreak home">
        <img src="<?= Html::e($socialImagePath) ?>" alt="Daybreak" class="logo-image">
      </a>
      <nav class="site-nav" aria-label="User navigation">
        <?php if ($_currentUser): ?>
          <a href="/sources" class="site-nav-link<?= ($activeNav ?? '') === 'sources' ? ' site-nav-link--active' : '' ?>">Sources</a>
          <a href="/search" class="site-nav-link<?= ($activeNav ?? '') === 'search' ? ' site-nav-link--active' : '' ?>">Search</a>
          <a href="/suggest" class="site-nav-link site-nav-link--suggest<?= ($activeNav ?? '') === 'suggest' ? ' site-nav-link--active' : '' ?>">Suggest</a>
          <a href="/feed" class="site-nav-link<?= ($activeNav ?? '') === 'myfeed' ? ' site-nav-link--active' : '' ?>">My Feed</a>
          <?php if ($_currentUser['role'] === 'admin'): ?>
            <a href="/admin" class="site-nav-link">Admin</a>
          <?php endif; ?>
          <a href="/settings/account" class="site-nav-link"><?= Html::e($_currentUser['display_name']) ?></a>
          <form method="post" action="/logout" class="site-nav-logout">
            <input type="hidden" name="_csrf" value="<?= Html::e(Csrf::token()) ?>">
            <button type="submit" class="site-nav-btn">Sign out</button>
          </form>
        <?php else: ?>
          <a href="/sources" class="site-nav-link<?= ($activeNav ?? '') === 'sources' ? ' site-nav-link--active' : '' ?>">Sources</a>
          <a href="/search" class="site-nav-link<?= ($activeNav ?? '') === 'search' ? ' site-nav-link--active' : '' ?>">Search</a>
          <a href="/login" class="site-nav-link site-nav-link--cta site-nav-link--cta-primary">Sign in</a>
          <a href="/register" class="site-nav-link site-nav-link--cta site-nav-link--cta-subtle">Register</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <?php if ($_showFilterBar): ?>
  <div class="filter-bar" role="navigation" aria-label="Filter articles">
    <div class="filter-bar-inner">
      <div class="feed-controls">
        <div class="feed-controls-chips">
          <a href="<?= Html::e($_base) ?>?days=<?= Html::e((string) $_winMode) ?>"
            class="cat-chip<?= $_cat === null ? ' is-active' : '' ?>">All</a>
          <?php foreach ($categories ?? [] as $cat): ?>
            <a href="<?= Html::e($_catBase . $cat['slug']) ?>?days=<?= Html::e((string) $_winMode) ?>"
              class="cat-chip<?= $_cat === $cat['slug'] ? ' is-active' : '' ?>"><?= Html::e($cat['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <form class="window-form" method="get" action="<?= $_filterAction ?>">
          <label for="window-days" class="sr-only">Time window</label>
          <span class="window-label" aria-hidden="true">Window</span>
          <select id="window-days" name="days" class="window-select">
            <?php foreach ($_winOpts as $val => $label): ?>
              <option value="<?= Html::e((string) $val) ?>" <?= (string) $_winMode === (string) $val ? ' selected' : '' ?>><?= Html::e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($_flash || $_flashErr): ?>
    <div class="flash-wrap">
      <?php if ($_flash): ?><div class="flash flash-success"><?= Html::e($_flash) ?></div><?php endif; ?>
      <?php if ($_flashErr): ?><div class="flash flash-error"><?= Html::e($_flashErr) ?></div><?php endif; ?>
    </div>
  <?php endif; ?>

  <main>
    <div class="content-grid<?= $_showWidgets ? '' : ' content-grid--single' ?>">
      <section class="feed-column" aria-label="News articles">
