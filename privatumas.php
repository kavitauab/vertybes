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
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
</head>
<body>
<header class="topbar-p"><a class="wordmark" href="/">
<svg width="24" height="14" viewBox="0 0 88 50"><path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="#D9432C"></path><path d="M55 3 L61 12 L55 15 L49 11 Z" fill="#F7EFDC"></path></svg>
<?= te('brand.name') ?></a></header>
<div class="policy">
  <a class="back-link" href="/">&larr; <?= te('policy.back') ?></a>
  <h1><?= te('policy.title') ?></h1>
  <p class="small-p" style="margin-bottom:1.2rem"><?= te('policy.updated') ?></p>
  <div class="body"><?= preg_replace('/^(\d+\..*)$/m', '<strong>$1</strong>', te('policy.full')) ?></div>
  <p class="small-p" style="margin-top:2rem"><?= te('policy.footer') ?></p>
</div>
</body>
</html>
