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
<title><?= te('cookies.page.title') ?> — <?= htmlspecialchars(getSetting('site_name', 'Vertybių testas')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
</head>
<body>
<div class="policy">
  <a class="back-link" href="index.php">&larr; <?= te('common.back') ?></a>
  <h1><?= te('cookies.page.title') ?></h1>
  <div class="body"><?= nl2br(te('cookies.page.body')) ?></div>
</div>
</body>
</html>
