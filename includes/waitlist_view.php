<?php /* Waiting-list landing — rendered by index.php while waitlist_mode = 1 */ ?>
<!DOCTYPE html>
<html lang="lt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= te('waitlist.title') ?></title>
<meta name="description" content="<?= te('waitlist.subtitle') ?>">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/public.css?v=<?= assetVersion('css/public.css') ?>">
</head>
<body>
<div class="page">
  <div class="shell">
    <div class="brand"><?= htmlspecialchars(getSetting('site_name', 'Vertybių testas')) ?></div>
    <h1 class="hero"><?= te('waitlist.title') ?></h1>
    <p class="sub"><?= te('waitlist.subtitle') ?></p>
    <p class="meta"><?= te('waitlist.meta') ?></p>

    <div class="card-p">
      <form id="waitlistForm" novalidate>
        <label for="wlEmail" class="sr-only" style="display:none"><?= te('waitlist.emailPlaceholder') ?></label>
        <input type="email" id="wlEmail" class="input-p" autocomplete="email"
               placeholder="<?= te('waitlist.emailPlaceholder') ?>" required>
        <!-- Honeypot: humans never see or fill this -->
        <input type="text" name="website" id="wlWebsite" tabindex="-1" autocomplete="off"
               style="position:absolute;left:-9999px;opacity:0;height:0;width:0" aria-hidden="true">
        <div class="field-error" id="wlError"></div>
        <label class="consent-line">
          <input type="checkbox" id="wlConsent">
          <span><?= str_replace('Privatumo politikoje',
                    '<a href="privatumas.php">Privatumo politikoje</a>',
                    te('waitlist.consent')) ?></span>
        </label>
        <div style="margin-top:1.25rem">
          <button type="submit" class="btn-p block" id="wlSubmit"><?= te('waitlist.cta') ?></button>
        </div>
        <div class="success-note" id="wlSuccess"></div>
      </form>
    </div>
  </div>
</div>
<footer class="footer-p">
  <a href="privatumas.php"><?= te('privacy.page.title') ?></a> ·
  <a href="slapukai.php"><?= te('cookies.page.title') ?></a>
</footer>
<script>
(function () {
  const form = document.getElementById('waitlistForm');
  const email = document.getElementById('wlEmail');
  const consent = document.getElementById('wlConsent');
  const err = document.getElementById('wlError');
  const ok = document.getElementById('wlSuccess');
  const btn = document.getElementById('wlSubmit');

  function showError(msg) {
    err.textContent = msg;
    err.classList.add('show');
    email.classList.add('invalid');
  }
  function clearError() {
    err.classList.remove('show');
    email.classList.remove('invalid');
  }

  form.addEventListener('submit', async function (e) {
    e.preventDefault();
    clearError();
    ok.classList.remove('show');

    const val = email.value.trim();
    if (!val || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
      showError(<?= json_encode(t('common.errorEmail'), JSON_UNESCAPED_UNICODE) ?>);
      email.focus();
      return;
    }
    if (!consent.checked) {
      showError(<?= json_encode(t('common.errorRequired'), JSON_UNESCAPED_UNICODE) ?>);
      return;
    }

    btn.disabled = true;
    try {
      const res = await fetch('api.php?action=joinWaitlist', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: val,
          website: document.getElementById('wlWebsite').value
        })
      });
      const d = await res.json();
      if (d.success) {
        ok.textContent = d.message || <?= json_encode(t('waitlist.success'), JSON_UNESCAPED_UNICODE) ?>;
        ok.classList.add('show');
        form.querySelectorAll('input, button').forEach(el => { if (el.type !== 'hidden') el.disabled = true; });
      } else {
        showError(d.message || <?= json_encode(t('common.errorGeneric'), JSON_UNESCAPED_UNICODE) ?>);
        btn.disabled = false;
      }
    } catch {
      showError(<?= json_encode(t('common.errorGeneric'), JSON_UNESCAPED_UNICODE) ?>);
      btn.disabled = false;
    }
  });
})();
</script>
</body>
</html>
