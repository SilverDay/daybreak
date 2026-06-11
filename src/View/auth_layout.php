<?php
declare(strict_types=1);

use Daybreak\Security\Html;
use Daybreak\Security\Csrf;

$_flash    = $_SESSION['flash']       ?? null;
$_flashErr = $_SESSION['flash_error'] ?? null;
if ($_flash)    { unset($_SESSION['flash']); }
if ($_flashErr) { unset($_SESSION['flash_error']); }
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= Html::e(Csrf::token()) ?>">
  <title><?= Html::e($title ?? 'Daybreak') ?> · Daybreak</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>

<header class="site-header">
  <div class="site-header-inner">
    <a href="/" class="logo">Daybreak</a>
    <span class="site-tagline">Security News</span>
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
