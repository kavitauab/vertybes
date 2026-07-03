<?php
$pageTitle = 'Vartotojai';
$activeNav = 'users';
require_once __DIR__ . '/includes/head.php';
requireAdmin();
?>
<div class="card">
  <div class="card-head">
    <h2>Administravimo vartotojai</h2>
    <button class="btn sm" onclick="newUser()"><i class="bi bi-plus-lg"></i> Naujas</button>
  </div>
  <p class="form-help" style="margin-bottom:1rem">
    <strong>Admin</strong> mato viską (nustatymus, vartotojus). <strong>Redaktorius</strong> gali keisti
    tekstus, klausimus, vertybes ir matyti kontaktus — tinka turinio tvarkymui.
  </p>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Vardas</th><th>El. paštas</th><th>Rolė</th><th>Aktyvus</th>
        <th>Pask. prisijungimas</th><th style="width:70px"></th></tr></thead>
      <tbody id="uBody"><tr><td colspan="6" class="empty">Kraunama…</td></tr></tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="uModal">
  <div class="modal">
    <h3 id="umTitle">Naujas vartotojas</h3>
    <div class="form-grid">
      <div class="form-row">
        <label>Vardas</label>
        <input type="text" id="umName">
      </div>
      <div class="form-row">
        <label>El. paštas</label>
        <input type="email" id="umEmail">
      </div>
    </div>
    <div class="form-grid">
      <div class="form-row">
        <label>Rolė</label>
        <select id="umRole">
          <option value="editor">Redaktorius</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="form-row">
        <label>Slaptažodis <span class="form-help" style="display:inline">(min. 10 simbolių; redaguojant — palik tuščią, jei nekeiti)</span></label>
        <input type="password" id="umPassword" autocomplete="new-password">
      </div>
    </div>
    <label class="consent-line" style="margin-top:0">
      <input type="checkbox" id="umActive"> <span>Aktyvus</span>
    </label>
    <div class="modal-actions">
      <button class="btn secondary" onclick="closeModal('uModal')">Atšaukti</button>
      <button class="btn" id="umSave">Išsaugoti</button>
    </div>
  </div>
</div>

<script>
let allUsers = [];
let editingId = null;

async function loadUsers() {
    const d = await apiCall('getUsers');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    allUsers = d.users;
    document.getElementById('uBody').innerHTML = allUsers.map(u => `
        <tr>
          <td><strong>${escapeHtml(u.name)}</strong></td>
          <td>${escapeHtml(u.email)}</td>
          <td><span class="badge ${u.role === 'admin' ? 'green' : 'gray'}">${u.role === 'admin' ? 'Admin' : 'Redaktorius'}</span></td>
          <td>${+u.is_active ? '<span class="badge green">Taip</span>' : '<span class="badge red">Ne</span>'}</td>
          <td>${fmtDate(u.last_login) || '—'}</td>
          <td><button class="btn secondary sm" onclick="editUser(${u.id})">
              <i class="bi bi-pencil"></i></button></td>
        </tr>`).join('');
}

function fillUserModal(u) {
    document.getElementById('umName').value = u ? u.name : '';
    document.getElementById('umEmail').value = u ? u.email : '';
    document.getElementById('umRole').value = u ? u.role : 'editor';
    document.getElementById('umPassword').value = '';
    document.getElementById('umActive').checked = u ? !!+u.is_active : true;
}

function newUser() {
    editingId = null;
    document.getElementById('umTitle').textContent = 'Naujas vartotojas';
    fillUserModal(null);
    openModal('uModal');
}

function editUser(id) {
    const u = allUsers.find(x => +x.id === id);
    if (!u) return;
    editingId = id;
    document.getElementById('umTitle').textContent = 'Redaguoti vartotoją';
    fillUserModal(u);
    openModal('uModal');
}

document.getElementById('umSave').addEventListener('click', async () => {
    const d = await apiCall('saveUser', {
        id: editingId,
        name: document.getElementById('umName').value.trim(),
        email: document.getElementById('umEmail').value.trim(),
        role: document.getElementById('umRole').value,
        password: document.getElementById('umPassword').value,
        is_active: document.getElementById('umActive').checked,
    }, 'POST');
    if (d.success) {
        showToast(d.message || 'Išsaugota', 'success');
        closeModal('uModal');
        loadUsers();
    } else {
        showToast(d.message || 'Klaida', 'error');
    }
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadUsers();</script>
