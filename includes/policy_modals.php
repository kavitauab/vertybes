<?php
/**
 * Privacy bottom sheet — the full 10-section policy in compact prose.
 * Any <a data-policy="privacy"> opens it; /privatumas stays the no-JS fallback.
 */
?>
<div class="sheet-overlay" id="policyPrivacy" role="dialog" aria-modal="true" aria-labelledby="policyPrivacyTitle">
  <div class="sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-head">
      <h2 id="policyPrivacyTitle"><?= te('policy.title') ?></h2>
      <button class="sheet-close" data-policy-close aria-label="Uždaryti">&times;</button>
    </div>
    <div class="sheet-body">
      <p style="color:var(--vt-muted);font-size:.85rem"><?= te('policy.updated') ?></p>
      <p><?= preg_replace('/^(\d+\..*)$/m', '<strong>$1</strong>', te('policy.full')) ?></p>
      <p style="color:var(--vt-muted);font-size:.85rem"><?= te('policy.footer') ?></p>
    </div>
    <div class="sheet-foot">
      <button class="btn-p" data-policy-close><?= te('policy.ok') ?></button>
    </div>
  </div>
</div>
<script>
(function () {
  window.openPolicyModal = function () {
    var el = document.getElementById('policyPrivacy');
    if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; window.__policyViewed = true; }
  };
  function closeAll() {
    document.querySelectorAll('.sheet-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    document.body.style.overflow = '';
  }
  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[data-policy]');
    if (link) {
      e.preventDefault();
      window.openPolicyModal();
      if (window.gtag) gtag('event', 'vertybiu_testas_privacy_open', { source: link.dataset.policySource || 'link' });
      return;
    }
    if (e.target.closest('[data-policy-close]')) closeAll();
  });
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
