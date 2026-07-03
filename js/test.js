/* Vertybių testas — public test flow (vanilla JS state machine).
 *
 * Screens: intro → privacy → cookies → questions (×N) → ai → review →
 *          compare-intro → duels (×10 [+ tiebreak]) → result.
 * Resume: getTestBootstrap returns saved session state; we jump to the
 * right screen so refresh never loses progress.
 */
(function () {
    'use strict';

    const app = document.getElementById('app');
    const S = {
        texts: {},
        questions: [],
        catalog: [],
        bookingUrl: '',
        session: null,
        // volatile flow state
        qIndex: 0,
        answers: {},          // question_key -> [strings]
        reviewAnswers: [],    // rows from getSuggestions
        comparisons: [],
        top5: [],
        pickerFor: null,
    };

    function T(key, replace) {
        let v = S.texts[key] || key;
        if (replace) for (const k in replace) v = v.split('{' + k + '}').join(replace[k]);
        return v;
    }

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g,
            m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    async function api(action, params, method = 'POST') {
        try {
            const opts = { method };
            if (method === 'POST') {
                opts.headers = { 'Content-Type': 'application/json' };
                opts.body = JSON.stringify(params || {});
            }
            const res = await fetch('api.php?action=' + action, opts);
            return await res.json();
        } catch {
            return { success: false, message: T('common.errorGeneric') };
        }
    }

    function render(html, opts) {
        const brand = (opts && opts.noBrand) ? '' :
            '<div class="brand">' + esc(document.title) + '</div>';
        app.innerHTML = brand + '<div class="screen">' + html + '</div>';
        window.scrollTo({ top: 0 });
    }

    function catalogByKey(key) {
        return S.catalog.find(v => v.value_key === key) || null;
    }

    /* ── Screens ─────────────────────────────────────────────── */

    function showIntro() {
        render(`
            <h1 class="hero">${esc(T('intro.title'))}</h1>
            <div class="orn" aria-hidden="true">✦</div>
            <p class="sub">${esc(T('intro.subtitle'))}</p>
            <p class="meta">${esc(T('intro.meta'))}</p>
            <div style="text-align:center">
              <button class="btn-p wide" id="startBtn">${esc(T('intro.cta'))}</button>
            </div>`, { noBrand: true });
        document.getElementById('startBtn').onclick = showPrivacy;
    }

    function showPrivacy() {
        render(`
            <div class="card-p">
              <h1 class="q-title">${esc(T('privacy.title'))}</h1>
              <p class="consent-body">${esc(T('privacy.body'))}</p>
              <p class="consent-note">${esc(T('privacy.confirm'))}
                 <a href="/privatumas" data-policy="privacy">${esc(T('privacy.link'))}</a></p>
              <div class="choice-row">
                <button class="btn-p ghost" id="noBtn">${esc(T('privacy.no'))}</button>
                <button class="btn-p" id="yesBtn">${esc(T('privacy.yes'))}</button>
              </div>
              <div class="field-error" id="declineNote"></div>
            </div>`);
        document.getElementById('yesBtn').onclick = showCookies;
        document.getElementById('noBtn').onclick = () => {
            const n = document.getElementById('declineNote');
            n.textContent = T('privacy.declined');
            n.classList.add('show');
        };
    }

    function showCookies() {
        render(`
            <div class="card-p">
              <h1 class="q-title">${esc(T('cookies.title'))}</h1>
              <p class="consent-body">${esc(T('cookies.body'))}</p>
              <p class="consent-note"><a href="/slapukai" data-policy="cookies">${esc(T('cookies.link'))}</a></p>
              <div class="choice-row">
                <button class="btn-p ghost" id="noBtn">${esc(T('cookies.no'))}</button>
                <button class="btn-p" id="yesBtn">${esc(T('cookies.yes'))}</button>
              </div>
              <div class="field-error" id="declineNote"></div>
            </div>`);
        document.getElementById('yesBtn').onclick = async function () {
            this.disabled = true;
            const d = await api('startTest', { privacy_accepted: true, cookies_accepted: true });
            if (!d.success) {
                this.disabled = false;
                const n = document.getElementById('declineNote');
                n.textContent = d.message || T('common.errorGeneric');
                n.classList.add('show');
                return;
            }
            S.qIndex = 0;
            showQuestion();
        };
        document.getElementById('noBtn').onclick = () => {
            const n = document.getElementById('declineNote');
            n.textContent = T('privacy.declined');
            n.classList.add('show');
        };
    }

    function showQuestion(notice) {
        const q = S.questions[S.qIndex];
        if (!q) { submitAnswers(); return; }
        const total = S.questions.length;
        const cur = S.qIndex + 1;
        const saved = S.answers[q.question_key] || [''];
        const pct = Math.round((S.qIndex / total) * 100);

        render(`
            <div class="progress-track"><div class="progress-fill" style="width:${pct}%"></div></div>
            <div class="progress-label">${esc(T('questions.progress', { current: cur, total }))}</div>
            ${notice ? `<div class="field-error show" style="margin-bottom:1rem">${esc(notice)}</div>` : ''}
            <h1 class="q-title">${esc(q.text)}</h1>
            <p class="q-hint">${esc(q.hint || '')}</p>
            <div id="answerRows"></div>
            <button class="add-answer" id="addAnswer">${esc(T('questions.addAnswer'))}</button>
            <div class="field-error" id="qError"></div>
            <div class="nav-row">
              ${S.qIndex > 0
                ? `<button class="btn-p ghost" id="backBtn">${esc(T('common.back'))}</button>`
                : '<span class="spacer"></span>'}
              <button class="btn-p" id="nextBtn">${esc(T('common.continue'))}</button>
            </div>`);

        const rows = document.getElementById('answerRows');
        function addRow(value) {
            if (rows.children.length >= +q.max_answers) return;
            const row = document.createElement('div');
            row.className = 'answer-row';
            row.innerHTML = `<input type="text" class="input-p" maxlength="500"
                                 placeholder="${esc(T('questions.answerPlaceholder'))}">` +
                (rows.children.length > 0 ? '<button class="answer-remove" title="Pašalinti">×</button>' : '');
            row.querySelector('input').value = value || '';
            const rm = row.querySelector('.answer-remove');
            if (rm) rm.onclick = () => row.remove();
            rows.appendChild(row);
        }
        saved.forEach(v => addRow(v));
        if (!rows.children.length) addRow('');
        document.getElementById('addAnswer').onclick = () => {
            addRow('');
            const inputs = rows.querySelectorAll('input');
            inputs[inputs.length - 1].focus();
        };

        function collect() {
            return Array.from(rows.querySelectorAll('input'))
                .map(i => i.value.trim()).filter(v => v !== '');
        }
        document.getElementById('nextBtn').onclick = () => {
            const vals = collect();
            if (!vals.length) {
                const e = document.getElementById('qError');
                e.textContent = T('common.errorRequired');
                e.classList.add('show');
                return;
            }
            S.answers[q.question_key] = vals;
            S.qIndex++;
            showQuestion();
        };
        const back = document.getElementById('backBtn');
        if (back) back.onclick = () => {
            S.answers[q.question_key] = collect();
            S.qIndex--;
            showQuestion();
        };
    }

    async function submitAnswers() {
        showAiLoading();
        const d = await api('saveAnswers', { answers: S.answers });
        if (!d.success) {
            S.qIndex = S.questions.length - 1;
            showQuestion(d.message || T('common.errorGeneric'));
            return;
        }
        requestSuggestions();
    }

    function showAiLoading() {
        render(`
            <div class="ai-loading">
              <div class="spinner"></div>
              <p class="sub">${esc(T('values.review.loading'))}</p>
            </div>`);
    }

    async function requestSuggestions() {
        const d = await api('getSuggestions', {});
        if (!d.success) {
            render(`
                <div class="card-p" style="text-align:center">
                  <p class="consent-body">${esc(d.message || T('common.errorGeneric'))}</p>
                  <button class="btn-p" id="retryBtn">${esc(T('common.continue'))}</button>
                </div>`);
            document.getElementById('retryBtn').onclick = () => { showAiLoading(); requestSuggestions(); };
            return;
        }
        S.reviewAnswers = d.answers;
        showReview();
    }

    function showReview(notice) {
        const items = S.reviewAnswers.map((a, i) => {
            const v = a.confirmed_value_key ? catalogByKey(a.confirmed_value_key) : null;
            const q = S.questions.find(x => x.question_key === a.question_key);
            return `
              <div class="review-item">
                <div class="review-q">${esc(q ? q.text : a.question_key)}</div>
                <div class="review-answer">„${esc(a.answer_text)}“</div>
                <button class="value-chip ${v ? '' : 'empty'}" data-i="${i}">
                  ${v ? esc(v.label_lt) : esc(T('values.review.searchPlaceholder'))}
                  <span class="chev">▾</span>
                </button>
              </div>`;
        }).join('');

        render(`
            <h1 class="q-title">${esc(T('values.review.title'))}</h1>
            <p class="q-hint">${esc(T('values.review.help'))}</p>
            ${notice ? `<div class="field-error show" style="margin-bottom:1rem">${esc(notice)}</div>` : ''}
            ${items}
            <div class="nav-row">
              <button class="btn-p ghost" id="backBtn">${esc(T('common.back'))}</button>
              <button class="btn-p" id="confirmBtn">${esc(T('common.continue'))}</button>
            </div>`);

        app.querySelectorAll('.value-chip').forEach(chip => {
            chip.onclick = () => openPicker(+chip.dataset.i);
        });
        document.getElementById('backBtn').onclick = () => { S.qIndex = 0; showQuestion(); };
        document.getElementById('confirmBtn').onclick = confirmValues;
    }

    /**
     * The picker lives on document.body, NOT inside the rendered screen:
     * .screen carries a transform animation, and a filled transform animation
     * makes the element the containing block for position:fixed descendants —
     * the overlay would anchor to the page content instead of the viewport.
     * (The policy popups avoid the same bug the same way.)
     */
    function ensurePicker() {
        if (document.getElementById('picker')) return;
        const el = document.createElement('div');
        el.className = 'picker-overlay';
        el.id = 'picker';
        el.innerHTML = `
            <div class="picker">
              <input type="text" class="input-p picker-search" id="pickerSearch"
                     placeholder="${esc(T('values.review.searchPlaceholder'))}">
              <div class="picker-list" id="pickerList"></div>
            </div>`;
        document.body.appendChild(el);

        document.getElementById('pickerSearch').addEventListener('input', renderPickerList);
        // Backdrop close: press must start AND end on the backdrop
        let downOnBackdrop = false;
        el.addEventListener('mousedown', e => { downOnBackdrop = e.target === el; });
        el.addEventListener('mouseup', e => {
            if (downOnBackdrop && e.target === el) closePicker();
            downOnBackdrop = false;
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && el.classList.contains('open')) closePicker();
        });
    }

    function openPicker(i) {
        ensurePicker();
        S.pickerFor = i;
        document.getElementById('pickerSearch').value = '';
        renderPickerList();
        document.getElementById('picker').classList.add('open');
        document.getElementById('pickerSearch').focus();
    }
    function closePicker() {
        document.getElementById('picker').classList.remove('open');
        S.pickerFor = null;
    }
    function renderPickerList() {
        const q = document.getElementById('pickerSearch').value.trim().toLowerCase();
        const list = S.catalog.filter(v =>
            !q || v.label_lt.toLowerCase().includes(q) ||
            (v.synonyms_lt || '').toLowerCase().includes(q));
        document.getElementById('pickerList').innerHTML = list.slice(0, 60).map(v => `
            <button class="picker-item" data-key="${esc(v.value_key)}">
              <div class="pi-label">${esc(v.label_lt)}</div>
              <div class="pi-meaning">${esc(v.meaning_lt || '')}</div>
            </button>`).join('');
        document.querySelectorAll('.picker-item').forEach(b => {
            b.onclick = () => {
                if (S.pickerFor !== null) {
                    S.reviewAnswers[S.pickerFor].confirmed_value_key = b.dataset.key;
                }
                closePicker();
                showReview();
            };
        });
    }

    async function confirmValues() {
        const missing = S.reviewAnswers.some(a => !a.confirmed_value_key);
        if (missing) { showReview(T('common.errorRequired')); return; }
        const d = await api('confirmValues', {
            confirmations: S.reviewAnswers.map(a => ({
                answer_id: a.id, value_key: a.confirmed_value_key,
            })),
        });
        if (!d.success) { showReview(d.message || T('common.errorGeneric')); return; }
        if (d.needs_more_answers) {
            S.qIndex = 0;
            showQuestion(T('questions.needMore'));
            return;
        }
        S.top5 = d.top5;
        S.comparisons = d.comparisons;
        showCompareIntro();
    }

    function showCompareIntro() {
        render(`
            <div class="card-p" style="text-align:center">
              <h1 class="q-title">${esc(T('compare.intro'))}</h1>
              <p class="q-hint" style="margin-top:.5rem">
                ${S.top5.map(v => esc(v.label_lt)).join(' · ')}
              </p>
              <button class="btn-p" id="goBtn">${esc(T('common.continue'))}</button>
            </div>`);
        document.getElementById('goBtn').onclick = showNextDuel;
    }

    function nextUnanswered() {
        return S.comparisons.find(c => !c.winner_value_key) || null;
    }

    function showNextDuel() {
        const c = nextUnanswered();
        if (!c) return; // resolution handled by saveComparison responses
        const isTb = +c.is_tiebreak === 1;
        const base = S.comparisons.filter(x => !+x.is_tiebreak);
        const done = base.filter(x => x.winner_value_key).length;
        const total = base.length;
        const left = catalogByKey(c.left_value_key);
        const right = catalogByKey(c.right_value_key);
        const pct = Math.round((done / total) * 100);

        render(`
            ${isTb ? `
              <h1 class="q-title" style="text-align:center">${esc(T('tiebreak.title'))}</h1>
              <p class="duel-help">${esc(T('tiebreak.body'))}</p>
            ` : `
              <div class="progress-track"><div class="progress-fill" style="width:${pct}%"></div></div>
              <div class="progress-label">${esc(T('compare.progress', { current: done + 1, total }))}</div>
              <h1 class="q-title" style="text-align:center">${esc(T('compare.title'))}</h1>
              <p class="duel-help">${esc(T('compare.help'))}</p>
            `}
            <div class="duel-row">
              <button class="duel-card" data-key="${esc(c.left_value_key)}">
                <div class="duel-label">${esc(left ? left.label_lt : c.left_value_key)}</div>
                <div class="duel-meaning">${esc(left ? left.meaning_lt : '')}</div>
              </button>
              <div class="duel-vs">ar</div>
              <button class="duel-card" data-key="${esc(c.right_value_key)}">
                <div class="duel-label">${esc(right ? right.label_lt : c.right_value_key)}</div>
                <div class="duel-meaning">${esc(right ? right.meaning_lt : '')}</div>
              </button>
            </div>
            <div class="field-error" id="duelError" style="text-align:center"></div>`);

        app.querySelectorAll('.duel-card').forEach(card => {
            card.onclick = async () => {
                app.querySelectorAll('.duel-card').forEach(x => x.disabled = true);
                const d = await api('saveComparison', {
                    pair_index: c.pair_index,
                    winner_value_key: card.dataset.key,
                });
                if (!d.success) {
                    const e = document.getElementById('duelError');
                    e.textContent = d.message || T('common.errorGeneric');
                    e.classList.add('show');
                    app.querySelectorAll('.duel-card').forEach(x => x.disabled = false);
                    return;
                }
                c.winner_value_key = card.dataset.key;
                handleProgress(d.progress);
            };
        });
    }

    function handleProgress(p) {
        if (!p) return;
        if (p.state === 'final') {
            showResult({ top: p.top_details, scores: p.scores });
        } else if (p.state === 'tiebreak') {
            // ensure the tiebreak row exists locally
            if (!S.comparisons.some(x => +x.is_tiebreak === 1)) {
                S.comparisons.push({
                    pair_index: 99,
                    left_value_key: p.tiebreak.left,
                    right_value_key: p.tiebreak.right,
                    winner_value_key: null,
                    is_tiebreak: 1,
                });
            }
            showNextDuel();
        } else {
            showNextDuel();
        }
    }

    function showResult(result) {
        const top = result.top || [];
        const first = top[0];
        const cards = top.map((v, i) => `
            <div class="result-card">
              <div class="result-rank">${i + 1}</div>
              <div class="result-main">
                <div class="result-value">${esc(v.label_lt)}</div>
                <div class="result-meaning">${esc(v.meaning_lt || '')}</div>
              </div>
            </div>`).join('');

        render(`
            <h1 class="hero" style="font-size:1.75rem">${esc(T('result.title'))}</h1>
            <div class="orn" aria-hidden="true">✦</div>
            <div class="result-cards">${cards}</div>
            ${first && first.tension_lt ? `
              <div class="tension-block">
                <div class="tension-title">${esc(T('result.tension'))}</div>
                <div>${esc(first.tension_lt)}</div>
              </div>` : ''}
            <div class="card-p email-block" id="emailBlock">
              <div class="email-title">${esc(T('result.emailTitle'))}</div>
              <div class="email-row">
                <input type="email" class="input-p" id="resEmail"
                       placeholder="${esc(T('result.emailPlaceholder'))}" autocomplete="email">
                <button class="btn-p" id="resEmailBtn">${esc(T('result.emailSend'))}</button>
              </div>
              <div class="field-error" id="resEmailError"></div>
              <div class="success-note" id="resEmailOk"></div>
            </div>
            <div class="cta-row">
              <a class="btn-p ghost" href="${esc(S.bookingUrl)}" target="_blank" rel="noopener">
                ${esc(T('result.cta'))}</a>
            </div>`);

        document.getElementById('resEmailBtn').onclick = async function () {
            const input = document.getElementById('resEmail');
            const err = document.getElementById('resEmailError');
            err.classList.remove('show');
            const email = input.value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                err.textContent = T('common.errorEmail');
                err.classList.add('show');
                return;
            }
            this.disabled = true;
            const d = await api('saveResultEmail', { email });
            if (d.success) {
                const ok = document.getElementById('resEmailOk');
                ok.textContent = d.message || T('result.emailSaved');
                ok.classList.add('show');
                input.disabled = true;
            } else {
                err.textContent = d.message || T('common.errorGeneric');
                err.classList.add('show');
                this.disabled = false;
            }
        };
    }

    /* ── Resume routing ──────────────────────────────────────── */

    function resume() {
        const s = S.session;
        if (!s) { showIntro(); return; }

        // rebuild local answers map
        S.answers = {};
        (s.answers || []).forEach(a => {
            (S.answers[a.question_key] = S.answers[a.question_key] || [])[a.answer_index] = a.answer_text;
        });
        for (const k in S.answers) S.answers[k] = S.answers[k].filter(v => v !== undefined);

        switch (s.status) {
            case 'result_ready':
            case 'email_captured':
                if (s.result) { showResult(s.result); return; }
                showIntro(); return;
            case 'comparing': {
                S.top5 = (s.top5 || []).map(k => catalogByKey(k)).filter(Boolean);
                S.comparisons = s.comparisons || [];
                const next = nextUnanswered();
                if (next) { showNextDuel(); return; }
                showIntro(); return;
            }
            case 'ai_suggested':
                S.reviewAnswers = (s.answers || []).map(a => ({
                    id: a.id, question_key: a.question_key, answer_text: a.answer_text,
                    suggested_value_key: a.suggested_value_key,
                    confirmed_value_key: a.confirmed_value_key,
                    confidence: a.confidence,
                }));
                showReview(); return;
            case 'answering':
            case 'consented':
                S.qIndex = 0;
                showQuestion(); return;
            default:
                showIntro();
        }
    }

    /* ── Boot ────────────────────────────────────────────────── */

    (async function boot() {
        const d = await api('getTestBootstrap', null, 'GET');
        if (!d.success) {
            app.innerHTML = '<div class="test-loading">Įvyko klaida. Perkrauk puslapį.</div>';
            return;
        }
        S.texts = d.texts;
        S.questions = d.questions;
        S.catalog = d.catalog;
        S.bookingUrl = d.booking_url;
        S.session = d.session;
        resume();
    })();
})();
