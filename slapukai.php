<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
$db = new Database();
require_once __DIR__ . '/helpers/app.php';
?><!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= te('cookies.popup.title') ?> — <?= te('brand.name') ?></title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
</head>
<body>
<header class="topbar-p"><a class="wordmark" href="/"><?= te('brand.name') ?></a></header>
<div class="policy">
  <a class="back-link" href="/">&larr; <?= te('common.back') ?></a>
  <h1><?= te('cookies.popup.title') ?></h1>
  <p style="color:var(--muted);margin-bottom:1.25rem"><?= te('cookies.popup.intro') ?></p>
  <?php foreach (['c1', 'c2'] as $c): ?>
  <div class="cookie-card">
    <div class="cookie-head">
      <span class="cookie-name"><?= te("cookies.$c.name") ?></span>
      <span class="chip-plain"><?= te('cookies.popup.required') ?></span>
    </div>
    <div class="cookie-desc"><?= te("cookies.$c.desc") ?></div>
    <div class="cookie-duration"><?= te('cookies.popup.duration') ?> <?= te("cookies.$c.duration") ?></div>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
