<?php
$pageTitle = 'Testo sesijos';
$activeNav = 'sessions';
require_once __DIR__ . '/includes/head.php';
?>
<div class="card">
  <div class="card-head">
    <h2>Paskutinės testo sesijos</h2>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Būsena</th><th>Atsakymų</th><th>Rezultatas</th>
        <th>Pradėta</th><th>Baigta</th></tr></thead>
      <tbody id="sBody"><tr><td colspan="6" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
</div>

<script>
const statusLabels = {
    created: 'Pradėta', consented: 'Sutikimai', answering: 'Atsakinėja',
    ai_suggested: 'AI pasiūlė', values_confirmed: 'Vertybės patvirtintos',
    comparing: 'Lygina', result_ready: 'Rezultatas', email_captured: 'Su el. paštu'
};

async function loadSessions() {
    const d = await apiCall('getSessions');
    const tb = document.getElementById('sBody');
    if (!d.success) { tb.innerHTML = '<tr><td colspan="6" class="empty">Klaida.</td></tr>'; return; }
    if (!d.sessions.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty">Sesijų kol kas nėra.</td></tr>';
        return;
    }
    tb.innerHTML = d.sessions.map(s => {
        let top = '—';
        try {
            const keys = JSON.parse(s.top_keys_json || 'null');
            if (Array.isArray(keys) && keys.length) top = keys.join(', ');
        } catch {}
        const done = ['result_ready','email_captured'].includes(s.status);
        return `<tr>
          <td class="form-help">${escapeHtml(s.uuid.slice(0, 8))}…</td>
          <td><span class="badge ${done ? 'green' : 'gray'}">${statusLabels[s.status] || escapeHtml(s.status)}</span></td>
          <td>${escapeHtml(s.answers)}</td>
          <td>${escapeHtml(top)}</td>
          <td>${fmtDate(s.started_at)}</td>
          <td>${fmtDate(s.completed_at) || '—'}</td>
        </tr>`;
    }).join('');
}
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadSessions();</script>
