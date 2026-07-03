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
      <label>Rezervacijos nuoroda („Rezervuoti pokalbį“)</label>
      <input type="url" id="sBookingUrl">
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
      <input type="text" id="sOpenaiModel">
    </div>
  </div>
  <label class="consent-line" style="margin-top:0">
    <input type="checkbox" id="sMock">
    <span><strong>Testavimo režimas</strong> — vertybės parenkamos pagal raktažodžius be AI užklausų (kol nėra rakto)</span>
  </label>
  <div class="form-row" style="margin-top:1rem">
    <label>AI instrukcija (system prompt)</label>
    <textarea id="sPrompt" rows="6"></textarea>
  </div>
</div>

<div class="card">
  <h2>Politikų versijos</h2>
  <p class="form-help" style="margin-bottom:1rem">Pakeitus politikos tekstą, pakelk versiją — sutikimai registruojami su versija.</p>
  <div class="form-grid">
    <div class="form-row">
      <label>Privatumo politikos versija</label>
      <input type="text" id="sPrivacyVer">
    </div>
    <div class="form-row">
      <label>Slapukų politikos versija</label>
      <input type="text" id="sCookieVer">
    </div>
  </div>
</div>

<div style="display:flex;justify-content:flex-end">
  <button class="btn" id="sSave"><i class="bi bi-check-lg"></i> Išsaugoti nustatymus</button>
</div>

<script>
async function loadSettings() {
    const d = await apiCall('getSettingsAdmin');
    if (!d.success) { showToast(d.message || 'Klaida', 'error'); return; }
    const s = {};
    d.settings.forEach(r => s[r.setting_key] = r.setting_value);
    document.getElementById('sSiteName').value = s.site_name || '';
    document.getElementById('sBookingUrl').value = s.booking_url || '';
    document.getElementById('sWaitlist').checked = s.waitlist_mode === '1';
    document.getElementById('sOpenaiKey').value = '';
    document.getElementById('sOpenaiKey').placeholder = s.openai_api_key === '••••' ? 'Raktas nustatytas (••••)' : 'sk-…';
    document.getElementById('sKeyNote').textContent = d.openai_key_from_env ? '(naudojamas serverio .env raktas)' : '';
    document.getElementById('sOpenaiModel').value = s.openai_model || '';
    document.getElementById('sMock').checked = s.ai_mock_mode === '1';
    document.getElementById('sPrompt').value = s.ai_system_prompt || '';
    document.getElementById('sPrivacyVer').value = s.privacy_policy_version || '';
    document.getElementById('sCookieVer').value = s.cookie_policy_version || '';
}

document.getElementById('sSave').addEventListener('click', async () => {
    const payload = {
        site_name: document.getElementById('sSiteName').value.trim(),
        booking_url: document.getElementById('sBookingUrl').value.trim(),
        waitlist_mode: document.getElementById('sWaitlist').checked ? '1' : '0',
        openai_model: document.getElementById('sOpenaiModel').value.trim(),
        ai_mock_mode: document.getElementById('sMock').checked ? '1' : '0',
        ai_system_prompt: document.getElementById('sPrompt').value.trim(),
        privacy_policy_version: document.getElementById('sPrivacyVer').value.trim(),
        cookie_policy_version: document.getElementById('sCookieVer').value.trim(),
    };
    const key = document.getElementById('sOpenaiKey').value.trim();
    if (key) payload.openai_api_key = key;
    const d = await apiCall('saveSettings', payload, 'POST');
    if (d.success) {
        showToast(d.message || 'Išsaugota', 'success');
        loadSettings();
    } else {
        showToast(d.message || 'Klaida', 'error');
    }
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
<script>loadSettings();</script>
