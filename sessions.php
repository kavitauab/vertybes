<?php
$pageTitle = 'Testo sesijos';
$activeNav = 'sessions';
require_once __DIR__ . '/includes/head.php';
$canDelete = isAdmin();
?>
<div class="card">
  <div class="card-head">
    <h2>Paskutinės testo sesijos</h2>
  </div>
  <p class="form-help" style="margin-bottom:1rem">Spausk eilutę, kad pamatytum atsakymus, vertybes ir rezultatą.</p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Būsena</th><th>Atsakymų</th><th>Rezultatas</th>
        <th>Pradėta</th><th>Baigta</th><?= $canDelete ? '<th style="width:60px"></th>' : '' ?></tr></thead>
      <tbody id="sBody"><tr><td colspan="7" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="sessionModal">
  <div class="modal modal-lg">
    <div class="card-head" style="margin-bottom:1rem">
      <h3 id="smTitle" style="margin:0">Sesija</h3>
      <button class="btn secondary sm" onclick="closeModal('sessionModal')">Uždaryti</button>
    </div>
    <div id="smBody"><div class="empty">Kraunama…</div></div>
  </div>
</div>

<script>
const CAN_DELETE = <?= $canDelete ? 'true' : 'false' ?>;
const statusLabels = {
    created: 'Pradėta', consented: 'Sutikimai', answering: 'Atsakinėja',
    ai_suggested: 'AI pasiūlė', values_confirmed: 'Vertybės patvirtintos',
    comparing: 'Lygina', result_ready: 'Rezultatas', email_captured: 'Su el. paštu'
};

async function loadSessions() {
    const d = await apiCall('getSessions');
    const tb = document.getElementById('sBody');
    if (!d.success) { tb.innerHTML = '<tr><td colspan="7" class="empty">Klaida.</td></tr>'; return; }
    if (!d.sessions.length) {
        tb.innerHTML = '<tr><td colspan="7" class="empty">Sesijų kol kas nėra.</td></tr>';
        return;
    }
    tb.innerHTML = d.sessions.map(s => {
        let top = '—';
        try {
            const keys = JSON.parse(s.top_keys_json || 'null');
            if (Array.isArray(keys) && keys.length) top = keys.join(', ');
        } catch {}
        const done = ['result_ready','email_captured'].includes(s.status);
        return `<tr class="row-click" onclick="openSession(${s.id})">
          <td class="form-help">#${s.id} · ${escapeHtml(s.uuid.slice(0, 8))}…</td>
          <td><span class="badge ${done ? 'green' : 'gray'}">${statusLabels[s.status] || escapeHtml(s.status)}</span></td>
          <td>${escapeHtml(s.answers)}</td>
          <td>${escapeHtml(top)}</td>
          <td>${fmtDate(s.started_at)}</td>
          <td>${fmtDate(s.completed_at) || '—'}</td>
          ${CAN_DELETE ? `<td><button class="btn danger sm" title="Ištrinti"
              onclick="event.stopPropagation(); deleteSession(${s.id})"><i class="bi bi-trash"></i></button></td>` : ''}
        </tr>`;
    }).join('');
}

async function openSession(id) {
    document.getElementById('smTitle').textContent = 'Sesija #' + id;
    document.getElementById('smBody').innerHTML = '<div class="empty">Kraunama…</div>';
    openModal('sessionModal');
    const d = await apiCall('getSessionDetail', { id });
    if (!d.success) {
        document.getElementById('smBody').innerHTML =
            `<div class="empty">${escapeHtml(d.message || 'Klaida')}</div>`;
        return;
    }

    let html = `<div class="detail-meta">
        <span class="badge ${['result_ready','email_captured'].includes(d.session.status) ? 'green' : 'gray'}">
            ${statusLabels[d.session.status] || escapeHtml(d.session.status)}</span>
        <span class="form-help">${escapeHtml(d.session.uuid)}</span>
        <span class="form-help">Pradėta ${fmtDate(d.session.started_at)}${d.session.completed_at ? ' · baigta ' + fmtDate(d.session.completed_at) : ''}</span>
      </div>`;

    if (d.lead) {
        html += `<div class="detail-block"><h4>Kontaktas</h4>
            <div>${escapeHtml(d.lead.email)} <span class="form-help">(${fmtDate(d.lead.created_at)})</span></div></div>`;
    }

    if (d.result) {
        html += `<div class="detail-block"><h4>Rezultatas</h4>
            <div><strong>${d.result.top.map(escapeHtml).join('</strong>, <strong>')}</strong></div>
            <div class="form-help">Taškai: ${escapeHtml(Object.entries(d.result.scores).map(([k, v]) => `${k} ${v}`).join(' · '))}</div></div>`;
    }

    if (d.answers.length) {
        html += '<div class="detail-block"><h4>Atsakymai ir vertybės</h4>' + d.answers.map(a => `
            <div class="detail-answer">
              <div class="form-help">${escapeHtml(a.question_text || a.question_key)}</div>
              <div>„${escapeHtml(a.answer_text)}“</div>
              <div class="form-help">
                ${a.confirmed_label
                    ? `→ <strong>${escapeHtml(a.confirmed_label)}</strong>${a.source === 'user' ? ' (pakeista ranka)' : ''}`
                    : '→ nepriskirta'}
                ${a.suggested_label && a.suggested_label !== a.confirmed_label
                    ? ` <s>${escapeHtml(a.suggested_label)}</s>` : ''}
                ${a.confidence !== null && a.confidence !== undefined ? ` · ${Math.round(a.confidence * 100)}%` : ''}
              </div>
            </div>`).join('') + '</div>';
    }

    if (d.comparisons.length) {
        html += '<div class="detail-block"><h4>Palyginimai</h4><div class="detail-duels">' +
            d.comparisons.map(c => `
              <div class="detail-duel${+c.is_tiebreak ? ' tiebreak' : ''}">
                ${+c.is_tiebreak ? '<span class="badge red">Lygiosios</span> ' : ''}
                ${escapeHtml(c.left_label || '?')} <span class="form-help">prieš</span> ${escapeHtml(c.right_label || '?')}
                <span class="form-help">→</span> <strong>${escapeHtml(c.winner_label || '—')}</strong>
              </div>`).join('') + '</div></div>';
    }

    document.getElementById('smBody').innerHTML = html;
}

async function deleteSession(id) {
    if (!confirm('Ištrinti sesiją #' + id + '? Atsakymai ir rezultatas bus pašalinti negrįžtamai (kontaktas išliks).')) return;
    const d = await apiCall('deleteSession', { id }, 'POST');
    if (d.success) {
        showToast(d.message || 'Ištrinta', 'success');
        closeModal('sessionModal');
        loadSessions();
    } else {
        showToast(d.message || 'Klaida', 'error');
    }
}
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadSessions();</script>
