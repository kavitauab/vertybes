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
<title><?= te('policy.title') ?> — <?= te('brand.name') ?></title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
</head>
<body>
<header class="topbar-p"><a class="wordmark" href="/"><?= te('brand.name') ?></a></header>
<div class="policy">
  <a class="back-link" href="/">&larr; <?= te('common.back') ?></a>
  <h1><?= te('policy.privacyTitle') ?></h1>
  <div class="body"><?= nl2br(te('policy.privacyBody')) ?></div>
  <h1 style="margin-top:2rem"><?= te('policy.aiTitle') ?></h1>
  <div class="body"><?= nl2br(te('policy.aiBody')) ?></div>
</div>
</body>
</html>
