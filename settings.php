<?php
$pageTitle = 'Nustatymai';
$activeNav = 'settings';
require_once __DIR__ . '/includes/head.php';
requireAdmin();
?>
<div class="card">
  <h2>Bendri</h2>
  <div class="form-grid">
    <div class="form-row">
      <label>Svetainės pavadinimas</label>
      <input type="text" id="sSiteName">
    </div>
    <div class="form-row">
      <label>Sutikimo versija</label>
      <input type="text" id="sConsentVer">
      <div class="form-help">Pakeitus privatumo/AI tekstus, pakelk versiją (pvz. v2) — sutikimai registruojami su versija.</div>
    </div>
  </div>
  <label class="consent-line" style="margin-top:0">
    <input type="checkbox" id="sWaitlist">
    <span><strong>Laukimo sąrašo režimas</strong> — pagrindinis puslapis rodo laukimo sąrašą vietoj testo</span>
  </label>
</div>

<div class="card">
  <h2>AI (OpenAI)</h2>
  <div class="form-grid">
    <div class="form-row">
      <label>API raktas <span class="form-help" id="sKeyNote" style="display:inline"></span></label>
      <input type="password" id="sOpenaiKey" placeholder="sk-…" autocomplete="off">
      <div class="form-help">Paliktas tuščias/nekeistas — raktas nekeičiamas. Raktas niekada nerodomas.</div>
    </div>
    <div class="form-row">
      <label>Modelis</label>
      <div style="display:flex;gap:.5rem">
        <input type="text" id="sOpenaiModel" list="modelList" style="flex:1" autocomplete="off">
        <button class="btn secondary" id="sFetchModels" type="button" title="Patikrina raktą ir parsiunčia galimų modelių sąrašą">
          <i class="bi bi-arrow-repeat"></i> Gauti modelius
        </button>
      </div>
      <datalist id="modelList"></datalist>
      <div class="form-help" id="sModelsNote"></div>
    </div>
  </div>
  <label class="consent-line" style="margin-top:0">
    <input type="checkbox" id="sMock">
    <span><strong>Testavimo režimas</strong> — vertybės parenkamos pagal raktažodžius be AI užklausų</span>
  </label>
</div>

<div class="card">
  <h2>MailerLite</h2>
  <p class="form-help" style="margin-bottom:1rem">API raktas laikomas serverio .env faile (MAILERLITE_TOKEN). Rezultato laišką siunčia MailerLite automatizacija.</p>
  <div class="form-grid">
    <div class="form-row">
      <label>Testo grupės ID</label>
      <input type="text" id="sMlTest">
    </div>
    <div class="form-row">
      <label>Marketingo grupės ID</label>
      <input type="text" id="sMlMarketing">
    </div>
  </div>
</div>

<div class="card">
  <h2>Nuorodos ir analitika</h2>
  <div class="form-grid">
    <div class="form-row">
      <label>VISION metodo nuoroda</label>
      <input type="url" id="sVision">
    </div>
    <div class="form-row">
      <label>„Kaip vyksta sesija“ nuoroda</label>
      <input type="url" id="sVisionSession">
    </div>
  </div>
  <div class="form-grid">
    <div class="form-row">
      <label>Facebook nuoroda</label>
      <input type="url" id="sFacebook">
    </div>
    <div class="form-row">
      <label>GA4 ID</label>
      <input type="text" id="sGa4">
    </div>
  </div>
  <div class="form-grid">
    <div class="form-row">
      <label>Meta Pixel ID</label>
      <input type="text" id="sPixel">
    </div>
    <div class="form-row">
      <label>Microsoft Clarity ID</label>
      <input type="text" id="sClarity">
    </div>
  </div>
</div>

<div style="display:flex;justify-content:flex-end">
  <button class="btn" id="sSave"><i class="bi bi-check-lg"></i> Išsaugoti nustatymus</button>
</div>

<script>
const FIELDS = {
    sSiteName: 'site_name', sConsentVer: 'consent_version',
    sOpenaiModel: 'openai_model',
    sMlTest: 'ml_group_test', sMlMarketing: 'ml_group_marketing',
    sVision: 'vision_url', sVisionSession: 'vision_session_url',
    sFacebook: 'facebook_url', sGa4: 'ga4_id', sPixel: 'meta_pixel_id', sClarity: 'clarity_id',
};

async function loadSettings() {
    const d = await apiCall('getSettingsAdmin');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    const s = {};
    d.settings.forEach(r => s[r.setting_key] = r.setting_value);
    for (const id in FIELDS) {
        const el = document.getElementById(id);
        if (el) el.value = s[FIELDS[id]] || '';
    }
    document.getElementById('sWaitlist').checked = s.waitlist_mode === '1';
    document.getElementById('sMock').checked = s.ai_mock_mode === '1';
    document.getElementById('sOpenaiKey').value = '';
    document.getElementById('sOpenaiKey').placeholder = s.openai_api_key === '••••' ? 'Raktas nustatytas (••••)' : 'sk-…';
    document.getElementById('sKeyNote').textContent = d.openai_key_from_env ? '(naudojamas serverio .env raktas)' : '';
}

document.getElementById('sFetchModels').addEventListener('click', async function () {
    this.disabled = true;
    const note = document.getElementById('sModelsNote');
    note.textContent = 'Tikrinama…';
    const d = await apiCall('getOpenAiModels',
        { api_key: document.getElementById('sOpenaiKey').value.trim() }, 'POST');
    this.disabled = false;
    if (!d.success) { note.textContent = d.message || 'Klaida'; showToast(d.message || 'Klaida', 'error'); return; }
    document.getElementById('modelList').innerHTML =
        d.models.map(m => `<option value="${escapeHtml(m)}">`).join('');
    note.textContent = `Raktas veikia ✓ — rasta ${d.models.length} modelių.`;
    showToast('Raktas veikia', 'success');
});

document.getElementById('sSave').addEventListener('click', async () => {
    const payload = {
        waitlist_mode: document.getElementById('sWaitlist').checked ? '1' : '0',
        ai_mock_mode: document.getElementById('sMock').checked ? '1' : '0',
    };
    for (const id in FIELDS) payload[FIELDS[id]] = document.getElementById(id).value.trim();
    const key = document.getElementById('sOpenaiKey').value.trim();
    if (key) payload.openai_api_key = key;
    const d = await apiCall('saveSettings', payload, 'POST');
    if (d.success) { showToast(d.message || 'Išsaugota', 'success'); loadSettings(); }
    else { showToast(d.message || 'Klaida', 'error'); }
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadSettings();</script>
