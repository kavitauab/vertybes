<?php /* Test app shell — screens rendered by js/test.js from getTestBootstrap. */ ?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= te('brand.name') ?></title>
<meta name="description" content="<?= te('intro.hero') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
<link rel="stylesheet" href="css/test.css?v=<?= assetVersion('css/test.css') ?>">
</head>
<body>
<header class="topbar-p"><a class="wordmark" href="/"><?= te('brand.name') ?></a></header>
<div class="page">
  <div class="shell" id="app">
    <div class="test-loading" id="bootLoading">Kraunama…</div>
  </div>
</div>
<footer class="footer-p">
  <a href="/privatumas" data-policy="privacy"><?= te('policy.privacyTitle') ?></a> ·
  <a href="/slapukai" data-policy="cookies"><?= te('cookies.popup.title') ?></a>
</footer>
<?php require __DIR__ . '/policy_modals.php'; ?>
<script src="js/test.js?v=<?= assetVersion('js/test.js') ?>"></script>
</body>
</html>
