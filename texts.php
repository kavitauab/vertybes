<?php
$pageTitle = 'Tekstai';
$activeNav = 'texts';
require_once __DIR__ . '/includes/head.php';
?>
<div class="card">
  <div class="card-head">
    <h2>Svetainės tekstai</h2>
    <input type="text" id="textSearch" placeholder="Ieškoti…" style="width:220px">
  </div>
  <p class="form-help" style="margin-bottom:1rem">
    Čia gali keisti visus lankytojui rodomus tekstus. Pakeitimai matomi iškart.
  </p>
  <div class="table-wrap">
    <table>
      <thead><tr><th style="width:22%">Kur naudojamas</th><th>Tekstas</th><th style="width:70px"></th></tr></thead>
      <tbody id="textsBody"><tr><td colspan="3" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="textModal">
  <div class="modal">
    <h3 id="tmTitle">Redaguoti tekstą</h3>
    <div class="form-row">
      <label id="tmContext"></label>
      <textarea id="tmValue" rows="5"></textarea>
      <div class="form-help" id="tmKey"></div>
    </div>
    <div class="modal-actions">
      <button class="btn secondary" onclick="closeModal('textModal')">Atšaukti</button>
      <button class="btn" id="tmSave">Išsaugoti</button>
    </div>
  </div>
</div>

<script>
let allTexts = [];
let editingKey = null;

async function loadTexts() {
    const d = await apiCall('getUiTextsAdmin');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    allTexts = d.texts;
    renderTexts();
}

function renderTexts() {
    const q = document.getElementById('textSearch').value.trim().toLowerCase();
    const rows = allTexts.filter(t =>
        !q || t.text_key.toLowerCase().includes(q) ||
        t.text_value.toLowerCase().includes(q) ||
        (t.context || '').toLowerCase().includes(q));
    const tb = document.getElementById('textsBody');
    if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="3" class="empty">Nieko nerasta.</td></tr>';
        return;
    }
    tb.innerHTML = rows.map(t => `
        <tr>
          <td><strong>${escapeHtml(t.context || t.text_key)}</strong>
              <div class="form-help">${escapeHtml(t.text_key)}</div></td>
          <td>${escapeHtml(t.text_value)}</td>
          <td><button class="btn secondary sm" onclick="editText('${escapeHtml(t.text_key)}')">
              <i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
}

function editText(key) {
    const t = allTexts.find(x => x.text_key === key);
    if (!t) return;
    editingKey = key;
    document.getElementById('tmContext').textContent = t.context || key;
    document.getElementById('tmKey').textContent = key;
    document.getElementById('tmValue').value = t.text_value;
    openModal('textModal');
}

document.getElementById('tmSave').addEventListener('click', async () => {
    const value = document.getElementById('tmValue').value.trim();
    if (!value) { showToast('Tekstas negali būti tuščias', 'error'); return; }
    const d = await apiCall('saveUiText', { text_key: editingKey, text_value: value }, 'POST');
    if (d.success) {
        showToast(d.message || 'Išsaugota', 'success');
        closeModal('textModal');
        loadTexts();
    } else {
        showToast(d.message || 'Klaida', 'error');
    }
});

document.getElementById('textSearch').addEventListener('input', renderTexts);
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadTexts();</script>
