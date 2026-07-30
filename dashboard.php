<?php
$pageTitle = 'Apžvalga';
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/head.php';
?>
<div class="stat-grid">
  <div class="stat"><div class="num" id="stSessions">–</div><div class="lbl">Pradėti testai</div>
    <div class="sub-num" id="stSessionsU"></div></div>
  <div class="stat"><div class="num" id="stCompleted">–</div><div class="lbl">Baigti testai</div>
    <div class="sub-num" id="stCompletedU"></div></div>
  <div class="stat"><div class="num" id="stConversion">–</div><div class="lbl">Užbaigiamumas</div></div>
  <div class="stat"><div class="num" id="stResult">–</div><div class="lbl">Kontaktai iš testo</div>
    <div class="sub-num" id="stEmailRate"></div></div>
  <div class="stat"><div class="num" id="stWaitlist">–</div><div class="lbl">Laukimo sąrašas</div></div>
</div>
<p class="form-help" style="margin:-.6rem 0 1.4rem">
  Pagrindinis skaičius — <strong>sesijos</strong>. Po juo — <strong>unikalūs žmonės</strong>:
  tas pats žmogus, kartodamas testą, sukuria naują sesiją, todėl sesijų visada daugiau.
  GA4 rodo dar mažiau — dalis lankytojų blokuoja analitiką arba atsisako slapukų.
</p>

<div class="dash-grid">
  <div class="card">
    <h2>Aktyvumas per 14 dienų</h2>
    <div class="mini-chart" id="dailyChart"></div>
    <div class="chart-legend">
      <span><i class="dot dot-sessions"></i> Testai</span>
      <span><i class="dot dot-leads"></i> Kontaktai</span>
    </div>
  </div>

  <div class="card">
    <h2>Testo piltuvėlis</h2>
    <div id="funnel"></div>
  </div>

  <div class="card">
    <h2>Dažniausios TOP vertybės</h2>
    <div id="topValues"><div class="empty">Kol kas nėra baigtų testų.</div></div>
  </div>

  <div class="card">
    <div class="card-head">
      <h2>Nauji kontaktai</h2>
      <a href="/leads" class="btn secondary sm">Visi</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>El. paštas</th><th>Šaltinis</th><th>Data</th></tr></thead>
        <tbody id="recentLeads"><tr><td colspan="3" class="empty">Kraunama…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const srcLabels = { waitlist: 'Laukimo sąrašas', result: 'Testas' };

async function loadStats() {
    const d = await apiCall('getStats');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    const s = d.stats;
    setText('stWaitlist', s.leads_waitlist);
    setText('stResult', s.leads_result);
    setText('stSessions', s.sessions_total);
    setText('stCompleted', s.sessions_completed);
    setText('stSessionsU', s.unique_total != null ? s.unique_total + ' unikalūs' : '');
    setText('stCompletedU', s.unique_completed != null ? s.unique_completed + ' unikalūs' : '');
    // email rate is measured against completions — that's the funnel step it follows
    setText('stEmailRate', s.sessions_completed
        ? Math.round(100 * s.leads_result / s.sessions_completed) + '% baigusiųjų' : '');
    setText('stConversion', s.sessions_total
        ? Math.round(100 * s.sessions_completed / s.sessions_total) + '%' : '—');

    // 14-day activity columns
    const maxDay = Math.max(1, ...s.daily.map(r => Math.max(r.sessions, r.leads)));
    document.getElementById('dailyChart').innerHTML = s.daily.map(r => `
        <div class="col" title="${escapeHtml(r.d)}: ${r.sessions} testai, ${r.leads} kontaktai">
          <div class="bars">
            <div class="bar bar-sessions" style="height:${Math.round(100 * r.sessions / maxDay)}%"></div>
            <div class="bar bar-leads" style="height:${Math.round(100 * r.leads / maxDay)}%"></div>
          </div>
          <div class="col-label">${escapeHtml(r.d.slice(8))}</div>
        </div>`).join('');

    // Funnel
    const maxF = Math.max(1, ...s.funnel.map(f => f.count));
    document.getElementById('funnel').innerHTML = s.funnel.map((f, i) => `
        <div class="funnel-row">
          <div class="funnel-label">${escapeHtml(f.label)}</div>
          <div class="funnel-track">
            <div class="funnel-bar" style="width:${Math.max(2, Math.round(100 * f.count / maxF))}%"></div>
          </div>
          <div class="funnel-num">${f.count}</div>
        </div>`).join('');

    // Top values
    if (s.top_values.length) {
        const maxV = s.top_values[0].count;
        document.getElementById('topValues').innerHTML = s.top_values.map(v => `
            <div class="funnel-row">
              <div class="funnel-label">${escapeHtml(v.label)}</div>
              <div class="funnel-track">
                <div class="funnel-bar soft" style="width:${Math.max(2, Math.round(100 * v.count / maxV))}%"></div>
              </div>
              <div class="funnel-num">${v.count}</div>
            </div>`).join('');
    }

    // Recent leads
    const tb = document.getElementById('recentLeads');
    tb.innerHTML = s.recent_leads.length
        ? s.recent_leads.map(l => `
            <tr>
              <td>${escapeHtml(l.email)}</td>
              <td><span class="badge ${l.source === 'waitlist' ? 'gray' : 'green'}">${srcLabels[l.source] || ''}</span></td>
              <td>${fmtDate(l.created_at)}</td>
            </tr>`).join('')
        : '<tr><td colspan="3" class="empty">Kontaktų kol kas nėra.</td></tr>';
}
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadStats();</script>
