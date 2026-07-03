/* Vertybių testas — shared admin utilities */

let csrfToken = null;

async function getCsrfToken() {
    if (csrfToken) return csrfToken;
    try {
        const res = await fetch('api.php?action=getCsrfToken');
        const d = await res.json();
        if (d.success) csrfToken = d.csrf_token;
        return csrfToken;
    } catch { return null; }
}

async function apiCall(action, params = {}, method = 'GET', _retried) {
    try {
        let url = `api.php?action=${encodeURIComponent(action)}`;
        const opts = { method, headers: {} };
        if (method === 'GET') {
            for (const k in params) {
                const v = params[k];
                if (v === null || v === undefined || v === '') continue;
                url += `&${encodeURIComponent(k)}=${encodeURIComponent(v)}`;
            }
        } else {
            const t = await getCsrfToken();
            opts.headers['Content-Type'] = 'application/json';
            if (t) opts.headers['X-CSRF-TOKEN'] = t;
            opts.body = JSON.stringify(params);
        }
        const res = await fetch(url, opts);
        if (res.status === 401) { window.location.href = 'login.php'; return { success: false }; }
        const d = await res.json();
        if (res.status === 403 && method !== 'GET' && !_retried &&
            d.message && /csrf/i.test(d.message)) {
            csrfToken = null;
            return apiCall(action, params, method, true);
        }
        return d;
    } catch (e) {
        console.error('apiCall failed', action, e);
        return { success: false, message: 'Tinklo klaida.' };
    }
}

function showToast(msg, kind = 'info', timeout = 3500) {
    let c = document.getElementById('toastContainer');
    if (!c) {
        c = document.createElement('div');
        c.className = 'toast-container'; c.id = 'toastContainer';
        c.setAttribute('role', 'status');
        c.setAttribute('aria-live', 'polite');
        document.body.appendChild(c);
    }
    const t = document.createElement('div');
    t.className = 'toast ' + kind;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 200); }, timeout);
}

function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = val; }

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g,
        m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

function fmtDate(s) {
    if (!s) return '';
    return s.replace('T', ' ').slice(0, 16);
}

/* ── Modals ──────────────────────────────────────────────────── */
let _modalOpener = null;

function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    _modalOpener = document.activeElement;
    m.classList.add('open');
    const f = m.querySelector('input, textarea, select, button');
    if (f) f.focus();
}

function closeModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('open');
    if (_modalOpener) { _modalOpener.focus(); _modalOpener = null; }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
    }
});

document.addEventListener('mousedown', e => {
    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
        const id = e.target.id;
        const up = ev => {
            if (ev.target === e.target) closeModal(id);
            document.removeEventListener('mouseup', up);
        };
        document.addEventListener('mouseup', up);
    }
});
