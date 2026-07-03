<?php
$pageTitle = 'Kontaktai';
$activeNav = 'leads';
require_once __DIR__ . '/includes/head.php';
?>
<div class="card">
  <div class="card-head">
    <h2>Surinkti kontaktai</h2>
    <div style="display:flex;gap:.6rem;align-items:center">
      <select id="sourceFilter" style="width:auto">
        <option value="">Visi šaltiniai</option>
        <option value="waitlist">Laukimo sąrašas</option>
        <option value="result">Testo rezultatas</option>
      </select>
      <a href="#" id="exportBtn" class="btn sm"><i class="bi bi-download"></i> CSV</a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>El. paštas</th><th>Šaltinis</th><th>Vertybės</th><th>Data</th></tr>
      </thead>
      <tbody id="leadsBody"><tr><td colspan="4" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
  <p class="form-help" id="leadsTotal" style="margin-top:.75rem"></p>
</div>

<script>
const sourceLabels = { waitlist: 'Laukimo sąrašas', result: 'Testo rezultatas' };

async function loadLeads() {
    const source = document.getElementById('sourceFilter').value;
    const d = await apiCall('getLeads', { source });
    const tb = document.getElementById('leadsBody');
    if (!d.success) { tb.innerHTML = '<tr><td colspan="4" class="empty">Klaida.</td></tr>'; return; }
    if (!d.leads.length) {
        tb.innerHTML = '<tr><td colspan="4" class="empty">Kontaktų kol kas nėra.</td></tr>';
    } else {
        tb.innerHTML = d.leads.map(l => `
            <tr>
              <td>${escapeHtml(l.email)}</td>
              <td><span class="badge ${l.source === 'waitlist' ? 'gray' : 'green'}">${sourceLabels[l.source] || escapeHtml(l.source)}</span></td>
              <td>${escapeHtml(l.top_values || '—')}</td>
              <td>${fmtDate(l.created_at)}</td>
            </tr>`).join('');
    }
    document.getElementById('leadsTotal').textContent = `Iš viso: ${d.total}`;
}

document.getElementById('sourceFilter').addEventListener('change', loadLeads);
document.getElementById('exportBtn').addEventListener('click', function (e) {
    e.preventDefault();
    const source = document.getElementById('sourceFilter').value;
    window.location.href = 'api.php?action=exportLeadsCsv' + (source ? '&source=' + source : '');
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadLeads();</script>
