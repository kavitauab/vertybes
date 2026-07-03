<?php
$pageTitle = 'Apžvalga';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/head.php';
?>
<div class="stat-grid">
  <div class="stat"><div class="num" id="stWaitlist">–</div><div class="lbl">Laukimo sąrašas</div></div>
  <div class="stat"><div class="num" id="stResult">–</div><div class="lbl">Kontaktai iš testo</div></div>
  <div class="stat"><div class="num" id="stSessions">–</div><div class="lbl">Pradėti testai</div></div>
  <div class="stat"><div class="num" id="stCompleted">–</div><div class="lbl">Baigti testai</div></div>
</div>

<div class="card">
  <div class="card-head">
    <h2>Nauji kontaktai per 7 dienas</h2>
    <a href="leads.php" class="btn secondary sm">Visi kontaktai</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Diena</th><th>Kontaktų</th></tr></thead>
      <tbody id="leads7d"><tr><td colspan="2" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
</div>

<script>
async function loadStats() {
    const d = await apiCall('getStats');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    const s = d.stats;
    setText('stWaitlist', s.leads_waitlist);
    setText('stResult', s.leads_result);
    setText('stSessions', s.sessions_total);
    setText('stCompleted', s.sessions_completed);
    const tb = document.getElementById('leads7d');
    if (!s.leads_7d.length) {
        tb.innerHTML = '<tr><td colspan="2" class="empty">Kol kas kontaktų nėra.</td></tr>';
    } else {
        tb.innerHTML = s.leads_7d.map(r =>
            `<tr><td>${escapeHtml(r.d)}</td><td>${escapeHtml(r.c)}</td></tr>`).join('');
    }
}
</script>
<?php $pageScript = null; require __DIR__ . '/includes/foot.php'; ?>
<script>loadStats();</script>
