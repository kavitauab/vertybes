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
<title><?= te('cookies.title') ?> — <?= te('brand.name') ?></title>
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
<link rel="stylesheet" href="css/test.css?v=<?= assetVersion('css/test.css') ?>">
</head>
<body>
<header class="topbar-p"><a class="wordmark" href="/">
<svg width="24" height="14" viewBox="0 0 88 50"><path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="#D9432C"></path><path d="M55 3 L61 12 L55 15 L49 11 Z" fill="#F7EFDC"></path></svg>
<?= te('brand.name') ?></a></header>
<div class="policy">
  <a class="back-link" href="/">&larr; <?= te('policy.back') ?></a>
  <h1><?= te('cookies.title') ?></h1>
  <p style="color:var(--vt-muted);margin:.6rem 0 1.2rem"><?= te('cookies.body') ?></p>
  <div class="cookie-cat">
    <div><div class="cc-name"><?= te('cookies.necessary.title') ?></div>
    <div class="cc-desc"><?= te('cookies.necessary.desc') ?></div></div>
    <div class="cc-state"><?= te('cookies.always') ?></div>
  </div>
  <div class="cookie-cat">
    <div><div class="cc-name"><?= te('cookies.stats.title') ?></div>
    <div class="cc-desc"><?= te('cookies.stats.desc') ?></div></div>
  </div>
</div>
</body>
</html>
