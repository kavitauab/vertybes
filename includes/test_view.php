<?php /* Test app shell — full flow lands here in Phase 3-5. */ ?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= te('intro.title') ?></title>
<meta name="description" content="<?= te('intro.subtitle') ?>">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
</head>
<body>
<div class="page">
  <div class="shell">
    <div class="brand"><?= htmlspecialchars(getSetting('site_name', 'Vertybių testas')) ?></div>
    <h1 class="hero"><?= te('intro.title') ?></h1>
    <p class="sub"><?= te('intro.subtitle') ?></p>
    <p class="meta"><?= te('intro.meta') ?></p>
    <div style="text-align:center">
      <button class="btn-p" disabled><?= te('intro.cta') ?></button>
      <p class="meta" style="margin-top:1rem">Testas ruošiamas — netrukus.</p>
    </div>
  </div>
</div>
<footer class="footer-p">
  <a href="privatumas.php"><?= te('privacy.page.title') ?></a> ·
  <a href="slapukai.php"><?= te('cookies.page.title') ?></a>
</footer>
</body>
</html>
