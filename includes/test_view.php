<?php /* Vertybės LT test app shell — screens rendered by js/test.js. */
$ga4 = getSetting('ga4_id', '');
$pixel = getSetting('meta_pixel_id', '');
$clarity = getSetting('clarity_id', '');
?><!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= te('brand.name') ?> — <?= te('intro.hero') ?></title>
<meta name="description" content="<?= te('intro.sub') ?>">
<meta property="og:title" content="<?= te('brand.name') ?>">
<meta property="og:description" content="<?= te('intro.hero') ?>">
<meta property="og:image" content="<?= htmlspecialchars((empty($_SERVER['HTTPS']) ? 'http' : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/assets/og-image.png') ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
<link rel="icon" type="image/png" sizes="192x192" href="assets/favicon-192.png">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
<link rel="stylesheet" href="css/test.css?v=<?= assetVersion('css/test.css') ?>">
<?php if ($ga4): ?>
<!-- GA4 + Consent Mode v2: analytics denied BEFORE config; accept fires the update -->
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied',
  ad_user_data: 'denied', ad_personalization: 'denied' });
gtag('js', new Date());
gtag('config', '<?= htmlspecialchars($ga4) ?>');
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($ga4) ?>"></script>
<?php endif; ?>
<?php if ($pixel): ?>
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('consent', 'revoke');
fbq('init', '<?= htmlspecialchars($pixel) ?>');
fbq('track', 'PageView');
</script>
<?php endif; ?>
<?php if ($clarity): ?>
<script>
(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})
(window, document, "clarity", "script", "<?= htmlspecialchars($clarity) ?>");
clarity('consent', false);
</script>
<?php endif; ?>
</head>
<body>
<header class="topbar-p"><a class="wordmark" href="/">
<svg width="24" height="14" viewBox="0 0 88 50"><path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="#D9432C"></path><path d="M55 3 L61 12 L55 15 L49 11 Z" fill="#F7EFDC"></path></svg>
<?= te('brand.name') ?></a></header>
<div class="page">
  <div class="shell" id="app">
    <div class="test-loading" id="bootLoading">Kraunama…</div>
  </div>
</div>
<footer class="footer-p">
  <a href="/privatumas" data-policy="privacy"><?= te('policy.title') ?></a>
</footer>
<?php require __DIR__ . '/policy_modals.php'; ?>
<script src="js/test.js?v=<?= assetVersion('js/test.js') ?>"></script>
</body>
</html>
