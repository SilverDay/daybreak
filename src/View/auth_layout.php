<?php

declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;
use Daybreak\Config;

$_flash    = $_SESSION['flash']       ?? null;
$_flashErr = $_SESSION['flash_error'] ?? null;
if ($_flash) {
  unset($_SESSION['flash']);
}
if ($_flashErr) {
  unset($_SESSION['flash_error']);
}

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

$seoTitle = (string) ($seoTitle ?? (($title ?? 'Daybreak') . ' · Daybreak'));
$seoDescription = (string) ($seoDescription ?? 'Authentication pages for Daybreak.');
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= Html::e(Csrf::token()) ?>">
  <meta name="description" content="<?= Html::e($seoDescription) ?>">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <link rel="canonical" href="<?= Html::e($canonicalUrl) ?>">
  <meta property="og:title" content="<?= Html::e($seoTitle) ?>">
  <meta property="og:description" content="<?= Html::e($seoDescription) ?>">
  <meta property="og:type" content="website">
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
  <title><?= Html::e($title ?? 'Daybreak') ?> · Daybreak</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body>

  <header class="site-header">
    <div class="site-header-inner">
      <a href="/" class="logo" aria-label="Daybreak home">
        <img src="<?= Html::e($socialImagePath) ?>" alt="Daybreak" class="logo-image">
      </a>
    </div>
  </header>

  <main class="auth-main">
    <div class="auth-card">
      <h1 class="auth-title"><?= Html::e($title ?? '') ?></h1>

      <?php if ($_flash): ?>
        <div class="flash flash-success"><?= Html::e($_flash) ?></div>
      <?php endif; ?>
      <?php if ($_flashErr): ?>
        <div class="flash flash-error"><?= Html::e($_flashErr) ?></div>
      <?php endif; ?>
