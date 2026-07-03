<?php /* Test app shell — screens rendered by js/test.js from getTestBootstrap. */ ?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= te('intro.title') ?></title>
<meta name="description" content="<?= te('intro.subtitle') ?>">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:ital,opsz,wght@0,9..144,400..700;1,9..144,400..700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
<link rel="stylesheet" href="css/test.css?v=<?= assetVersion('css/test.css') ?>">
</head>
<body>
<div class="page">
  <div class="shell" id="app">
    <div class="brand"><?= htmlspecialchars(getSetting('site_name', 'Vertybių testas')) ?></div>
    <div class="test-loading" id="bootLoading">Kraunama…</div>
  </div>
</div>
<footer class="footer-p">
  <a href="/privatumas" data-policy="privacy"><?= te('privacy.page.title') ?></a> ·
  <a href="/slapukai" data-policy="cookies"><?= te('cookies.page.title') ?></a>
</footer>
<?php require __DIR__ . '/policy_modals.php'; ?>
<script src="js/test.js?v=<?= assetVersion('js/test.js') ?>"></script>
</body>
</html>
