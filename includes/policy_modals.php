<?php
/**
 * Policy bottom sheets (design: "privatumo politika pop up", "slapukai pop up").
 * Any <a data-policy="privacy|cookies"> opens the sheet instead of navigating;
 * the standalone pages remain as a no-JS / direct-link fallback.
 */
?>
<div class="sheet-overlay" id="policyPrivacy" role="dialog" aria-modal="true" aria-labelledby="policyPrivacyTitle">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-head">
      <h2 id="policyPrivacyTitle"><?= te('policy.title') ?></h2>
      <button class="sheet-close" data-policy-close aria-label="<?= te('common.back') ?>">&times;</button>
    </div>
    <div class="sheet-body">
      <h3><?= te('policy.privacyTitle') ?></h3>
      <p><?= nl2br(te('policy.privacyBody')) ?></p>
      <h3><?= te('policy.aiTitle') ?></h3>
      <p><?= nl2br(te('policy.aiBody')) ?></p>
    </div>
    <div class="sheet-foot">
      <button class="btn-p" data-policy-close><?= te('policy.ok') ?></button>
    </div>
  </div>
</div>
<div class="sheet-overlay" id="policyCookies" role="dialog" aria-modal="true" aria-labelledby="policyCookiesTitle">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-head">
      <h2 id="policyCookiesTitle"><?= te('cookies.popup.title') ?></h2>
      <button class="sheet-close" data-policy-close aria-label="<?= te('common.back') ?>">&times;</button>
    </div>
    <div class="sheet-body">
      <p><?= te('cookies.popup.intro') ?></p>
      <?php foreach (['c1', 'c2'] as $c): ?>
      <div class="cookie-card">
        <div class="cookie-head">
          <span class="cookie-name"><?= te("cookies.$c.name") ?></span>
          <span class="chip-plain"><?= te('cookies.popup.required') ?></span>
        </div>
        <div class="cookie-desc"><?= te("cookies.$c.desc") ?></div>
        <div class="cookie-duration"><?= te('cookies.popup.duration') ?> <?= te("cookies.$c.duration") ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="sheet-foot">
      <button class="btn-p" data-policy-close><?= te('cookies.popup.close') ?></button>
    </div>
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
    document.querySelectorAll('.sheet-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    document.body.style.overflow = '';
  }
  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[data-policy]');
    if (link) { e.preventDefault(); window.openPolicyModal(link.dataset.policy); return; }
    if (e.target.closest('[data-policy-close]')) closeAll();
  });
  // Backdrop close only when the press starts AND ends on the backdrop
  var downOn = null;
  document.addEventListener('mousedown', function (e) {
    downOn = (e.target.classList && e.target.classList.contains('sheet-overlay')) ? e.target : null;
  });
  document.addEventListener('mouseup', function (e) {
    if (downOn && e.target === downOn) closeAll();
    downOn = null;
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
})();
</script>
