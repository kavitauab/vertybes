<?php
/**
 * Policy popups — shared by the waitlist landing and the test app.
 * Any <a data-policy="privacy|cookies"> opens the popup instead of navigating;
 * the standalone pages stay as a no-JS / direct-link fallback.
 */
?>
<div class="policy-overlay" id="policyPrivacy" role="dialog" aria-modal="true" aria-labelledby="policyPrivacyTitle">
  <div class="policy-card">
    <div class="policy-head">
      <h2 id="policyPrivacyTitle"><?= te('privacy.page.title') ?></h2>
      <button class="policy-close" data-policy-close aria-label="<?= te('common.back') ?>">&times;</button>
    </div>
    <div class="policy-body"><?= nl2br(te('privacy.page.body')) ?></div>
  </div>
</div>
<div class="policy-overlay" id="policyCookies" role="dialog" aria-modal="true" aria-labelledby="policyCookiesTitle">
  <div class="policy-card">
    <div class="policy-head">
      <h2 id="policyCookiesTitle"><?= te('cookies.page.title') ?></h2>
      <button class="policy-close" data-policy-close aria-label="<?= te('common.back') ?>">&times;</button>
    </div>
    <div class="policy-body"><?= nl2br(te('cookies.page.body')) ?></div>
  </div>
</div>
<script>
(function () {
  var map = { privacy: 'policyPrivacy', cookies: 'policyCookies' };
  window.openPolicyModal = function (kind) {
    var el = document.getElementById(map[kind]);
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
  };
  function closeAll() {
    document.querySelectorAll('.policy-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    document.body.style.overflow = '';
  }
  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[data-policy]');
    if (link) { e.preventDefault(); window.openPolicyModal(link.dataset.policy); return; }
    if (e.target.closest('[data-policy-close]')) closeAll();
  });
  // Backdrop close only when the press starts AND ends on the backdrop —
  // a drag-select or stray duplicate event can't dismiss the popup.
  var downOnBackdrop = null;
  document.addEventListener('mousedown', function (e) {
    downOnBackdrop = (e.target.classList && e.target.classList.contains('policy-overlay')) ? e.target : null;
  });
  document.addEventListener('mouseup', function (e) {
    if (downOnBackdrop && e.target === downOnBackdrop) closeAll();
    downOnBackdrop = null;
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
})();
</script>
