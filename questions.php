<?php
$pageTitle = 'Klausimai';
$activeNav = 'questions';
require_once __DIR__ . '/includes/head.php';
?>
<div class="card">
  <div class="card-head">
    <h2>Testo klausimai</h2>
  </div>
  <p class="form-help" style="margin-bottom:1rem">
    4 atviri klausimai, į kuriuos lankytojas atsako laisvu tekstu. Kiekvienam
    klausimui galima leisti iki 6 atsakymų.
  </p>
  <div class="table-wrap">
    <table>
      <thead><tr><th style="width:40px">#</th><th>Klausimas</th><th>Užuomina</th>
        <th style="width:90px">Maks. ats.</th><th style="width:80px">Aktyvus</th><th style="width:70px"></th></tr></thead>
      <tbody id="qBody"><tr><td colspan="6" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="qModal">
  <div class="modal">
    <h3>Redaguoti klausimą</h3>
    <div class="form-row">
      <label>Klausimo tekstas</label>
      <textarea id="qmText" rows="3"></textarea>
    </div>
    <div class="form-row">
      <label>Užuomina (rodoma po klausimu)</label>
      <textarea id="qmHint" rows="2"></textarea>
    </div>
    <div class="form-grid">
      <div class="form-row">
        <label>Maks. atsakymų</label>
        <input type="number" id="qmMax" min="1" max="10">
      </div>
      <div class="form-row">
        <label>Eilės nr.</label>
        <input type="number" id="qmOrder" min="1" max="20">
      </div>
    </div>
    <label class="consent-line" style="margin-top:0">
      <input type="checkbox" id="qmActive"> <span>Aktyvus</span>
    </label>
    <div class="modal-actions">
      <button class="btn secondary" onclick="closeModal('qModal')">Atšaukti</button>
      <button class="btn" id="qmSave">Išsaugoti</button>
    </div>
  </div>
</div>

<script>
let allQuestions = [];
let editingId = null;

async function loadQuestions() {
    const d = await apiCall('getQuestionsAdmin');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    allQuestions = d.questions;
    const tb = document.getElementById('qBody');
    tb.innerHTML = allQuestions.map(q => `
        <tr>
          <td>${escapeHtml(q.sort_order)}</td>
          <td>${escapeHtml(q.text)}</td>
          <td class="form-help">${escapeHtml(q.hint || '')}</td>
          <td>${escapeHtml(q.max_answers)}</td>
          <td>${+q.is_active ? '<span class="badge green">Taip</span>' : '<span class="badge gray">Ne</span>'}</td>
          <td><button class="btn secondary sm" onclick="editQuestion(${q.id})">
              <i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
}

function editQuestion(id) {
    const q = allQuestions.find(x => +x.id === id);
    if (!q) return;
    editingId = id;
    document.getElementById('qmText').value = q.text;
    document.getElementById('qmHint').value = q.hint || '';
    document.getElementById('qmMax').value = q.max_answers;
    document.getElementById('qmOrder').value = q.sort_order;
    document.getElementById('qmActive').checked = !!+q.is_active;
    openModal('qModal');
}

document.getElementById('qmSave').addEventListener('click', async () => {
    const d = await apiCall('saveQuestion', {
        id: editingId,
        text: document.getElementById('qmText').value.trim(),
        hint: document.getElementById('qmHint').value.trim(),
        max_answers: +document.getElementById('qmMax').value,
        sort_order: +document.getElementById('qmOrder').value,
        is_active: document.getElementById('qmActive').checked,
    }, 'POST');
    if (d.success) {
        showToast(d.message || 'Išsaugota', 'success');
        closeModal('qModal');
        loadQuestions();
    } else {
        showToast(d.message || 'Klaida', 'error');
    }
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadQuestions();</script>
