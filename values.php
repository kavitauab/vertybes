<?php
$pageTitle = 'Vertybės';
$activeNav = 'values';
require_once __DIR__ . '/includes/head.php';
?>
<div class="card">
  <div class="card-head">
    <h2>Vertybių katalogas <span class="badge gray" id="valCount"></span></h2>
    <div style="display:flex;gap:.6rem;align-items:center">
      <input type="text" id="valSearch" placeholder="Ieškoti…" style="width:220px">
      <button class="btn sm" onclick="newValue()"><i class="bi bi-plus-lg"></i> Nauja</button>
    </div>
  </div>
  <p class="form-help" style="margin-bottom:1rem">
    Kanoninis vertybių sąrašas: AI priskiria atsakymams tik šias vertybes.
    Sinonimai padeda AI atpažinti vertybę atsakyme.
  </p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Vertybė</th><th>Ką tai reiškia</th><th>Sinonimai</th>
        <th style="width:80px">Aktyvi</th><th style="width:70px"></th></tr></thead>
      <tbody id="valBody"><tr><td colspan="5" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="valModal">
  <div class="modal">
    <h3 id="vmTitle">Redaguoti vertybę</h3>
    <div class="form-grid">
      <div class="form-row">
        <label>Pavadinimas</label>
        <input type="text" id="vmLabel">
      </div>
      <div class="form-row" id="vmKeyRow">
        <label>Raktas (naujoms; pvz. <code>ramybe</code>)</label>
        <input type="text" id="vmKey" placeholder="tik a-z, 0-9 ir _">
      </div>
    </div>
    <div class="form-row">
      <label>Ką tai reiškia (iki 10 žodžių)</label>
      <input type="text" id="vmMeaning">
    </div>
    <div class="form-row">
      <label>Vidinė įtampa</label>
      <textarea id="vmTension" rows="3"></textarea>
    </div>
    <div class="form-row">
      <label>Sinonimai (kableliais)</label>
      <input type="text" id="vmSynonyms">
    </div>
    <label class="consent-line" style="margin-top:0">
      <input type="checkbox" id="vmActive"> <span>Aktyvi (AI gali ją siūlyti)</span>
    </label>
    <label class="consent-line" style="margin-top:.4rem">
      <input type="checkbox" id="vmCore"> <span>Pagrindinė (rodoma pasirinkimo tinklelyje)</span>
    </label>
    <div class="modal-actions">
      <button class="btn secondary" onclick="closeModal('valModal')">Atšaukti</button>
      <button class="btn" id="vmSave">Išsaugoti</button>
    </div>
  </div>
</div>

<script>
let allValues = [];
let editingId = null;

async function loadValues() {
    const d = await apiCall('getValuesAdmin');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    allValues = d.values;
    renderValues();
}

function renderValues() {
    const q = document.getElementById('valSearch').value.trim().toLowerCase();
    const rows = allValues.filter(v =>
        !q || v.label_lt.toLowerCase().includes(q) ||
        (v.synonyms_lt || '').toLowerCase().includes(q) ||
        (v.meaning_lt || '').toLowerCase().includes(q));
    document.getElementById('valCount').textContent = `${rows.length} / ${allValues.length}`;
    const tb = document.getElementById('valBody');
    if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="5" class="empty">Nieko nerasta.</td></tr>';
        return;
    }
    tb.innerHTML = rows.map(v => `
        <tr>
          <td><strong>${escapeHtml(v.label_lt)}</strong>
              ${+v.is_core ? ' <span class="badge green">Pagrindinė</span>' : ''}
              ${+v.is_custom ? ' <span class="badge red">Vartotojo</span>' : ''}
              <div class="form-help">${escapeHtml(v.value_key)}</div></td>
          <td>${escapeHtml(v.meaning_lt || '')}</td>
          <td class="form-help">${escapeHtml(v.synonyms_lt || '')}</td>
          <td>${+v.is_active ? '<span class="badge green">Taip</span>' : '<span class="badge gray">Ne</span>'}</td>
          <td><button class="btn secondary sm" onclick="editValue(${v.id})">
              <i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
}

function fillModal(v) {
    document.getElementById('vmLabel').value = v ? v.label_lt : '';
    document.getElementById('vmKey').value = v ? v.value_key : '';
    document.getElementById('vmKey').disabled = !!v;
    document.getElementById('vmMeaning').value = v ? (v.meaning_lt || '') : '';
    document.getElementById('vmTension').value = v ? (v.tension_lt || '') : '';
    document.getElementById('vmSynonyms').value = v ? (v.synonyms_lt || '') : '';
    document.getElementById('vmActive').checked = v ? !!+v.is_active : true;
    document.getElementById('vmCore').checked = v ? !!+v.is_core : false;
}

function editValue(id) {
    const v = allValues.find(x => +x.id === id);
    if (!v) return;
    editingId = id;
    document.getElementById('vmTitle').textContent = 'Redaguoti vertybę';
    fillModal(v);
    openModal('valModal');
}

function newValue() {
    editingId = null;
    document.getElementById('vmTitle').textContent = 'Nauja vertybė';
    fillModal(null);
    openModal('valModal');
}

document.getElementById('vmSave').addEventListener('click', async () => {
    const payload = {
        id: editingId,
        label_lt: document.getElementById('vmLabel').value.trim(),
        meaning_lt: document.getElementById('vmMeaning').value.trim(),
        tension_lt: document.getElementById('vmTension').value.trim(),
        synonyms_lt: document.getElementById('vmSynonyms').value.trim(),
        is_active: document.getElementById('vmActive').checked,
        is_core: document.getElementById('vmCore').checked,
    };
    if (!editingId) payload.value_key = document.getElementById('vmKey').value.trim();
    const d = await apiCall('saveValue', payload, 'POST');
    if (d.success) {
        showToast(d.message || 'Išsaugota', 'success');
        closeModal('valModal');
        loadValues();
    } else {
        showToast(d.message || 'Klaida', 'error');
    }
});

document.getElementById('valSearch').addEventListener('input', renderValues);
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadValues();</script>
