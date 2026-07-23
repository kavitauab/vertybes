/* Vertybės LT — test flow v3 (PERDAVIMAS.md 2026-07-22).
 * Flow: Intro → Consent → Q1–Q4 → AI analysis → Comparison → Duels →
 *       (Tie-break) → Result → Kitas žingsnis (email) → Sent.
 * No review screen; answers stay editable until analysis; ties resolve silently.
 */
(function () {
    'use strict';

    var app = document.getElementById('app');

    var S = {
        texts: {}, questions: [], links: {}, session: null,
        consentChecked: false,
        qIndex: 0,
        answers: {},        // question_key -> [strings]
        growStopped: {},    // question_key -> bool
        lastCoach: {},      // question_key -> last shown filled count
        candidates: [],     // [{value_key,label_lt,confidence,mentions,evidence}]
        comparisons: [],
        result: null,
        sentEmail: '',
        analysisAborted: false,
    };

    var DOT_RAMP = ['#D9432C', '#E8845F', '#A0553D', '#6E2312', '#C4A98E'];

    /* ── Utilities ───────────────────────────────────────── */

    function T(key, replace) {
        var v = S.texts[key] || key;
        if (replace) for (var k in replace) v = v.split('{' + k + '}').join(replace[k]);
        return v;
    }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }
    function api(action, params, method) {
        method = method || 'POST';
        var opts = { method: method };
        if (method === 'POST') {
            opts.headers = { 'Content-Type': 'application/json' };
            opts.body = JSON.stringify(params || {});
        }
        return fetch('api.php?action=' + action, opts)
            .then(function (r) { return r.json(); })
            .catch(function () { return { success: false, message: T('common.errorGeneric') }; });
    }
    function render(html, inTest) {
        document.body.classList.toggle('in-test', !!inTest);
        app.innerHTML = '<div class="screen">' + html + '</div>';
        window.scrollTo({ top: 0 });
    }
    function candidate(key) {
        for (var i = 0; i < S.candidates.length; i++) {
            if (S.candidates[i].value_key === key) return S.candidates[i];
        }
        return null;
    }

    /* ── Attribution + analytics (Consent Mode gated) ────── */

    function attribution() {
        var stored = null;
        try { stored = JSON.parse(localStorage.getItem('vt_attribution') || 'null'); } catch (e) {}
        if (stored) return stored;
        var p = new URLSearchParams(location.search);
        var a = { source: p.get('source') || '', referral_code: p.get('referral_code') || '' };
        if (a.source || a.referral_code) {
            a.first_seen = new Date().toISOString();
            try { localStorage.setItem('vt_attribution', JSON.stringify(a)); } catch (e) {}
            return a;
        }
        return { source: '', referral_code: '' };
    }

    function track(name, params) {
        var a = attribution();
        var p = Object.assign({
            source: a.source || '(none)',
            referral_code: a.referral_code || '(none)',
        }, params || {});
        if (window.gtag) gtag('event', 'vertybiu_testas_' + name, p);
    }

    function cookieChoice() { try { return localStorage.getItem('vt_cookie_choice') || ''; } catch (e) { return ''; } }
    function setCookieChoice(v, via) {
        try { localStorage.setItem('vt_cookie_choice', v); } catch (e) {}
        if (v === 'all') {
            if (window.gtag) gtag('consent', 'update', { analytics_storage: 'granted' });
            if (window.fbq) { fbq('consent', 'grant'); }
            if (window.clarity) { clarity('consent', true); }
            track('cookie_accept', via ? { via: via } : {});
        } else {
            track('cookie_decline', via ? { via: via } : {});
        }
    }

    /* ── Icons / marks ───────────────────────────────────── */

    var MOUNTAIN = function (w, h, extra) {
        return '<svg width="' + w + '" height="' + h + '" viewBox="0 0 88 50" ' + (extra || '') + '>' +
            '<path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="var(--vt-accent)"></path>' +
            '<path d="M55 3 L61 12 L55 15 L49 11 Z" fill="var(--vt-bg)"></path></svg>';
    };
    var CHECK = function (size, stroke) {
        return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="' +
            (stroke || 'var(--vt-accent)') + '" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">' +
            '<polyline points="20 6 9 17 4 12"></polyline></svg>';
    };
    var CHEVRON = '&#8249;';

    /* ── Shared partials ─────────────────────────────────── */

    function progressHead(label, pct, backFn) {
        window.__backFn = backFn || null;
        return '<div class="progress-head"><div class="progress-row">' +
            (backFn ? '<button class="back-btn" onclick="__backFn()" aria-label="Atgal">' + CHEVRON + '</button>' : '') +
            '<div class="progress-meta">' +
            '<div class="progress-label-p">' + esc(label) + '</div>' +
            '<div class="progress-track-p"><div class="progress-fill-p" style="width:' + pct + '%"></div></div>' +
            '</div></div><hr class="progress-hr"></div>';
    }

    /* ── 1 · Intro + cookie sheet ────────────────────────── */

    function showIntro() {
        render(
            '<div class="intro-logo">' +
            '<svg width="150" height="150" viewBox="0 0 148 148">' +
            '<circle cx="74" cy="74" r="74" fill="var(--vt-soft)" opacity=".55"></circle>' +
            '<path d="M28 108 L64 42 L82 66 L96 34 L134 108 Z" fill="var(--vt-accent)"></path>' +
            '<path d="M96 34 L103 45 L96 49 L89 44 Z" fill="var(--vt-bg)"></path>' +
            '<ellipse cx="40" cy="94" rx="52" ry="14" fill="var(--vt-bg)" opacity=".85"></ellipse>' +
            '<ellipse cx="104" cy="106" rx="48" ry="13" fill="var(--vt-bg)" opacity=".45"></ellipse>' +
            '</svg></div>' +
            '<h1 class="h1-p h-center intro-hero">' + esc(T('intro.hero')) + '</h1>' +
            '<p class="sub-p sub-center intro-sub">' + esc(T('intro.sub')) + '</p>' +
            '<div class="card-p intro-checks">' +
            [1, 2, 3, 4].map(function (i) {
                return '<div class="intro-check">' + CHECK(14) + '<span>' + esc(T('intro.bullet' + i)) + '</span></div>';
            }).join('') +
            '</div>' +
            '<button class="btn-p" id="startBtn">' + esc(T('intro.cta')) + '</button>' +
            '<div class="intro-cookie-link"><button class="link-quiet" id="cookieLink">' + esc(T('cookies.link')) + '</button></div>'
        );
        document.getElementById('startBtn').onclick = function () {
            track('start');
            if (document.cookie.indexOf('consent_given=1') !== -1) { startSession(); return; }
            showConsent();
        };
        document.getElementById('cookieLink').onclick = function () { openCookieSheet(true); };
        if (!cookieChoice()) openCookieSheet(false);
    }

    function openCookieSheet(expanded) {
        var el = document.getElementById('cookieSheet');
        if (!el) {
            el = document.createElement('div');
            el.className = 'sheet-overlay';
            el.id = 'cookieSheet';
            document.body.appendChild(el);
        }
        function layer(showSettings) {
            el.innerHTML =
                '<div class="sheet"><div class="sheet-handle"></div>' +
                '<div class="sheet-head"><h2>' + esc(T('cookies.title')) + '</h2>' +
                '<button class="sheet-close" id="ckClose">&times;</button></div>' +
                '<div class="sheet-body">' +
                '<p>' + esc(T('cookies.body')) + '</p>' +
                (showSettings ?
                    '<div class="cookie-cat"><div><div class="cc-name">' + esc(T('cookies.necessary.title')) + '</div>' +
                    '<div class="cc-desc">' + esc(T('cookies.necessary.desc')) + '</div></div>' +
                    '<div class="cc-state">' + esc(T('cookies.always')) + '</div></div>' +
                    '<div class="cookie-cat"><div><div class="cc-name">' + esc(T('cookies.stats.title')) + '</div>' +
                    '<div class="cc-desc">' + esc(T('cookies.stats.desc')) + '</div></div>' +
                    '<label class="check-line" style="margin-top:.15rem"><input type="checkbox" id="ckStats"' +
                    (cookieChoice() !== 'necessary' ? ' checked' : '') + '></label></div>' +
                    '<div class="cookie-actions"><button class="btn-p" id="ckSave">' + esc(T('cookies.saveChoice')) + '</button></div>'
                    :
                    '<div class="cookie-actions">' +
                    '<button class="btn-p" id="ckAll">' + esc(T('cookies.acceptAll')) + '</button>' +
                    '<button class="btn-p ghost" id="ckNec">' + esc(T('cookies.onlyNecessary')) + '</button>' +
                    '<button class="link-quiet" id="ckSettings">' + esc(T('cookies.settings')) + '</button>' +
                    '</div>') +
                '</div></div>';
            document.getElementById('ckClose').onclick = close;
            if (showSettings) {
                document.getElementById('ckSave').onclick = function () {
                    setCookieChoice(document.getElementById('ckStats').checked ? 'all' : 'necessary', 'settings');
                    close();
                };
            } else {
                document.getElementById('ckAll').onclick = function () { setCookieChoice('all'); close(); };
                document.getElementById('ckNec').onclick = function () { setCookieChoice('necessary'); close(); };
                document.getElementById('ckSettings').onclick = function () { layer(true); };
            }
        }
        function close() { el.classList.remove('open'); document.body.style.overflow = ''; }
        layer(!!expanded);
        el.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    /* ── 2 · Prieš pradedant ─────────────────────────────── */

    function showConsent(withError) {
        render(
            '<h1 class="h1-p" style="margin-top:1.2rem">' + esc(T('consent.title')) + '</h1>' +
            '<div class="card-p consent-card"><p>' + esc(T('consent.aiInfo')) + '</p></div>' +
            [1, 2, 3, 4].map(function (i) {
                return '<div class="guide-row">' + CHECK(13) + '<span>' + esc(T('consent.g' + i)) + '</span></div>';
            }).join('') +
            '<p class="policy-link-line">' + esc(T('consent.linkIntro')) + ' ' +
            '<a href="/privatumas" data-policy="privacy">' + esc(T('consent.linkText')) + '</a>.</p>' +
            '<label class="check-line' + (withError ? ' invalid' : '') + '">' +
            '<input type="checkbox" id="consentCheck"' + (S.consentChecked ? ' checked' : '') + '>' +
            '<span>' + esc(T('consent.checkbox')) + '</span></label>' +
            '<div class="field-error' + (withError ? ' show' : '') + '">' + esc(T('consent.error')) + '</div>' +
            '<div style="margin-top:1.4rem"><button class="btn-p" id="consentBtn"' +
            (S.consentChecked ? '' : ' disabled') + '>Tęsti</button></div>'
        );
        document.getElementById('consentCheck').onchange = function () {
            S.consentChecked = this.checked;
            document.getElementById('consentBtn').disabled = !this.checked;
        };
        document.getElementById('consentBtn').onclick = function () {
            if (!S.consentChecked) { showConsent(true); return; }
            track('consent_complete', { policy_viewed: window.__policyViewed === true });
            startSession();
        };
    }

    function startSession() {
        var a = attribution();
        api('startTest', {
            privacy_accepted: true, cookies_accepted: true,
            source: a.source, referral_code: a.referral_code,
        }).then(function (d) {
            if (!d.success) { showConsent(true); return; }
            document.cookie = 'consent_given=1; path=/; max-age=31536000; samesite=lax';
            S.qIndex = 0;
            showQuestion();
        });
    }

    /* ── 3 · Questions (auto-grow, coaching, min-2 gate) ─── */

    var COACH_FALLBACK = '💡';
    var autosaveTimer = null;

    function coachFor(n) {
        var raw = T('coach.' + Math.min(n, 6));
        var parts = raw.split('|');
        return { emoji: parts[0] || COACH_FALLBACK, text: parts[1] || raw };
    }

    function showQuestion(errorBanner) {
        var q = S.questions[S.qIndex];
        if (!q) { finishQuestions(); return; }
        var total = S.questions.length;
        var cur = S.qIndex + 1;
        var qk = q.question_key;
        var saved = S.answers[qk] || [];
        var rows = Math.max(2, saved.length);
        var maxRows = +q.max_answers || 6;

        var fields = '';
        for (var i = 0; i < rows; i++) {
            var ph = i === 2 ? T('questions.kasdar')
                : (q.placeholders && q.placeholders[Math.min(i, (q.placeholders.length || 1) - 1)]) || '';
            fields += '<div class="answer-row-p">' +
                '<div class="field-card"><input type="text" maxlength="500" data-idx="' + i + '"' +
                ' placeholder="' + esc(ph) + '" value="' + esc(saved[i] || '') + '"></div>' +
                '<button class="answer-remove-p" data-remove="' + i + '" title="Pašalinti">&times;</button>' +
                '</div>';
        }

        render(
            progressHead(T('questions.progress', { current: cur, total: total }), Math.round(cur / total * 100),
                function () {
                    collect();
                    if (S.qIndex === 0) { showIntro(); } else { S.qIndex--; showQuestion(); }
                }) +
            '<h1 class="h1-p" style="margin-top:1.2rem">' + esc(q.text) + '</h1>' +
            '<p class="q-instruction">' + esc(q.hint || '') + '</p>' +
            (errorBanner ? '<div class="error-banner q-error">' + esc(errorBanner) + '</div>' : '') +
            '<div class="answers-stack" id="answerStack">' + fields + '</div>' +
            '<div class="coach-line" id="coachLine"></div>' +
            '<button class="btn-p" id="nextBtn">Tęsti</button>' +
            '<div class="autosave-note">' + esc(T('questions.autosave')) + '</div>' +
            '<div class="gate-overlay" id="gateModal"><div class="gate-modal">' +
            '<h3>' + esc(T('gate.title')) + '</h3>' +
            '<p>' + esc(T('gate.b1')) + '</p><p>' + esc(T('gate.b2')) + '</p><p>' + esc(T('gate.b3')) + '</p>' +
            '<button class="btn-p" id="gateBtn">' + esc(T('gate.cta')) + '</button>' +
            '</div></div>',
            true
        );

        // iOS: keep the focused field visible above the keyboard accessory bar
        document.getElementById('answerStack').addEventListener('focusin', function (e) {
            if (e.target.tagName === 'INPUT') {
                setTimeout(function () {
                    e.target.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }, 300);
            }
        });

        var stack = document.getElementById('answerStack');

        function inputs() { return [].slice.call(stack.querySelectorAll('input')); }
        function collect() {
            S.answers[qk] = inputs().map(function (i2) { return i2.value; });
        }
        function filledCount() {
            return inputs().filter(function (i2) { return i2.value.trim() !== ''; }).length;
        }

        function renderCoach(n, animate) {
            var line = document.getElementById('coachLine');
            if (!line) return;
            var c = coachFor(n);
            var emoji = c.emoji === 'MOUNTAIN' ? MOUNTAIN(20, 11, 'style="flex-shrink:0"') :
                '<span class="coach-emoji">' + esc(c.emoji) + '</span>';
            line.className = 'coach-line';
            line.innerHTML = emoji + '<span' + (animate ? ' style="animation:vtCoachIn .28s ease both"' : '') + '>' +
                esc(c.text) + '</span>';
            if (n >= 1 && n < 6) {
                clearTimeout(line.__t);
                line.__t = setTimeout(function () {
                    line.className = 'coach-line helper';
                    line.innerHTML = '<span>' + esc(T('coach.helper')) + '</span>';
                }, 2600);
            }
        }
        renderCoach(filledCount(), false);
        S.lastCoach[qk] = filledCount();

        function refreshAnswersUI() {
            var list = inputs();
            var filled = filledCount();
            // auto-grow: all rows filled and fewer than max → append one
            if (!S.growStopped[qk] && list.length < maxRows && filled === list.length) {
                collect();
                S.answers[qk].push('');
                track('answer_add', { question_number: cur, answer_index: list.length, via: 'auto' });
                var keep = document.activeElement === list[list.length - 1] ? list.length - 1 : null;
                showQuestion();
                if (keep !== null) {
                    var ni = document.querySelectorAll('#answerStack input');
                    ni[keep].focus();
                    var v = ni[keep].value;
                    ni[keep].setSelectionRange(v.length, v.length);
                }
                return;
            }
            if (filled !== S.lastCoach[qk]) {
                S.lastCoach[qk] = filled;
                renderCoach(filled, true);
            }
        }

        stack.addEventListener('input', function () {
            collect();
            refreshAnswersUI();
            if (autosaveTimer) clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(function () { autosave(qk); }, 1600);
        });

        // "✓ Atsakymas išsaugotas" flash on blur of an edited non-empty field
        stack.addEventListener('focusout', function (e) {
            var inp = e.target;
            if (!inp || inp.tagName !== 'INPUT') return;
            if (inp.value.trim() === '' || inp.__flashedFor === inp.value) return;
            inp.__flashedFor = inp.value;
            var row = inp.closest('.answer-row-p');
            var old = row.querySelector('.saved-flash');
            if (old) old.remove();
            var pill = document.createElement('span');
            pill.className = 'saved-flash';
            pill.innerHTML = CHECK(9, 'var(--vt-muted)') + ' ' + esc(T('questions.savedFlash'));
            row.appendChild(pill);
            setTimeout(function () { pill.remove(); }, 1000);
            autosave(qk);
        });

        stack.querySelectorAll('[data-remove]').forEach(function (b) {
            b.onclick = function () {
                collect();
                var idx = +b.dataset.remove;
                var wasEmpty = (S.answers[qk][idx] || '').trim() === '';
                S.answers[qk].splice(idx, 1);
                if (wasEmpty) S.growStopped[qk] = true;  // user intent respected
                showQuestion();
            };
        });

        document.getElementById('gateBtn').onclick = function () {
            document.getElementById('gateModal').classList.remove('open');
            var before = filledCount();
            track('single_answer_add_more_clicked', { answers_before: before, answers_after: before });
            var empty = inputs().find(function (i2) { return i2.value.trim() === ''; });
            if (empty) empty.focus();
        };

        var nextBtn = document.getElementById('nextBtn');
        nextBtn.onclick = function () {
            collect();
            var vals = S.answers[qk].filter(function (v) { return v.trim() !== ''; });
            if (vals.length === 0) { renderCoach(0, true); return; }
            if (vals.length === 1) {
                track('single_answer_popup_shown', { question_number: cur, answers_count: 1 });
                document.getElementById('gateModal').classList.add('open');
                return;
            }
            nextBtn.disabled = true;
            api('saveQuestionAnswers', { question_key: qk, answers: vals }).then(function (d) {
                if (!d.success) { nextBtn.disabled = false; showQuestion(d.message || T('common.errorGeneric')); return; }
                track('question_answered', { question_number: cur, answers_count: vals.length });
                S.answers[qk] = vals;
                S.qIndex++;
                showQuestion();
            });
        };
    }

    function autosave(qk) {
        var vals = (S.answers[qk] || []).filter(function (v) { return v.trim() !== ''; });
        if (vals.length) api('saveQuestionAnswers', { question_key: qk, answers: vals });
    }

    function finishQuestions() {
        // Q4 Tęsti → auto-save acknowledgment → analysis with NO extra click
        showAnalysis();
    }

    /* ── 4 · AI analysis (the transition screen) ─────────── */

    function showAnalysis() {
        S.analysisAborted = false;
        track('analysis_view');
        render(
            '<div class="ai-screen">' +
            '<div class="ai-saved" id="aiSaved">' + CHECK(12) + ' ' + esc(T('analysis.saved')) + '</div>' +
            '<div class="ai-mountain">' +
            '<svg width="176" height="100" viewBox="0 0 88 50" style="display:block;margin:0 auto">' +
            '<path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="var(--vt-accent)" style="animation:vtFillIn 3.4s ease infinite"></path>' +
            '<path d="M55 3 L61 12 L55 15 L49 11 Z" fill="var(--vt-bg)" style="animation:vtFillIn 3.4s ease infinite"></path>' +
            '<path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="none" stroke="var(--vt-accent)" stroke-width="2.5" ' +
            'stroke-linejoin="round" stroke-linecap="round" stroke-dasharray="340" ' +
            'style="animation:vtDraw 3.4s ease infinite"></path></svg></div>' +
            '<h1 class="h1-p h-center">' + esc(T('analysis.title')) + '</h1>' +
            '<div class="ai-steps">' +
            '<div class="ai-step" id="st1">' + CHECK(13) + '<span>' + esc(T('analysis.step1')) + '</span></div>' +
            '<div class="ai-step" id="st2">' + CHECK(13) + '<span>' + esc(T('analysis.step2')) + '</span></div>' +
            '<div class="ai-step" id="st3"><span class="dot-pulse"></span><span>' + esc(T('analysis.step3')) + '</span></div>' +
            '</div>' +
            '<div class="ai-edit" id="aiEdit"><button class="link-quiet" id="editAnswers">' + esc(T('analysis.edit')) + '</button></div>' +
            '<div class="ai-seed">' + esc(T('analysis.seed')) + '</div>' +
            '</div>',
            true
        );
        setTimeout(function () { var e = document.getElementById('st1'); if (e) e.classList.add('on'); }, 300);
        setTimeout(function () { var e = document.getElementById('st2'); if (e) e.classList.add('on'); }, 1400);
        setTimeout(function () { var e = document.getElementById('st3'); if (e) e.classList.add('on'); }, 2500);
        // the escape hatch fades after ~2.8 s
        setTimeout(function () {
            var s1 = document.getElementById('aiSaved'), s2 = document.getElementById('aiEdit');
            if (s1) s1.style.opacity = '0';
            if (s2) { s2.style.opacity = '0'; s2.style.pointerEvents = 'none'; }
        }, 2800);
        document.getElementById('editAnswers').onclick = function () {
            S.analysisAborted = true;
            track('analysis_edit_click');
            S.qIndex = 0;
            showQuestion();
        };

        var started = Date.now();
        api('analyzeAnswers', {}).then(function (d) {
            if (S.analysisAborted) return;
            var wait = Math.max(0, 3400 - (Date.now() - started));
            setTimeout(function () {
                if (S.analysisAborted) return;
                if (!d.success) { showAnalysisError(); return; }
                if (d.needs_more_answers) {
                    S.qIndex = 0;
                    showQuestion(T('questions.needMore'));
                    return;
                }
                S.candidates = d.values;
                S.comparisons = d.comparisons;
                showComparison();
            }, wait);
        });
    }

    function showAnalysisError() {
        render(
            '<div class="ai-screen" style="padding-top:3rem">' +
            MOUNTAIN(64, 36) +
            '<h1 class="h1-p h-center" style="margin:1.2rem 0 .6rem">' + esc(T('analysis.failed')) + '</h1>' +
            '<div style="max-width:280px;margin:1.4rem auto 0">' +
            '<button class="btn-p" id="retryBtn">' + esc(T('analysis.retry')) + '</button></div>' +
            '</div>'
        );
        document.getElementById('retryBtn').onclick = function () { showAnalysis(); };
    }

    /* ── 5 · Comparison (reasoning reveal) ───────────────── */

    function showComparison() {
        var cards = S.candidates.map(function (v, i) {
            var chips = (v.mentions || []).map(function (m, j) {
                return '<span class="cmp-chip" style="animation-delay:' +
                    (1.05 + i * .38 + .18 + j * .1).toFixed(2) + 's">' + esc(m) + '</span>';
            }).join('');
            return '<div class="cmp-card' + (i === 0 ? ' first' : '') + '" style="animation-delay:' +
                (1.05 + i * .38).toFixed(2) + 's">' +
                '<div class="cmp-name-row"><span class="cmp-dot" style="background:' +
                DOT_RAMP[i % DOT_RAMP.length] + '"></span>' +
                '<span class="cmp-name">' + esc(v.label_lt) + '</span></div>' +
                (chips ? '<div class="cmp-chips">' + chips + '</div>' : '') +
                '</div>';
        }).join('');

        var total = S.comparisons.filter(function (c) { return !+c.is_tiebreak; }).length;
        var ctaDelay = (1.05 + S.candidates.length * .38 + .5).toFixed(2);

        render(
            '<h1 class="h1-p" style="margin-top:1.2rem">' + esc(T('comparison.title')) + '</h1>' +
            '<p class="sub-p" style="margin-top:.4rem">' + esc(T('comparison.sub')) + '</p>' +
            '<div class="cmp-status" id="cmpStatus">🤖 ' + esc(T('comparison.analyzing')) + '</div>' +
            '<div class="cmp-stack">' + cards + '</div>' +
            '<div class="cmp-cta" style="animation-delay:' + ctaDelay + 's">' +
            '<div class="cmp-lead">' + esc(T('comparison.next')) + '</div>' +
            '<button class="btn-p" id="cmpGo">' + esc(T('comparison.cta')) + '</button>' +
            '<div class="cmp-caption">' + esc(T('comparison.caption')) + '</div>' +
            '<div class="cmp-restart"><button class="link-quiet" id="cmpRestart">' + esc(T('comparison.restart')) + '</button></div>' +
            '</div>',
            true
        );
        setTimeout(function () {
            var st = document.getElementById('cmpStatus');
            if (st) st.style.opacity = '0';
        }, 1000);
        document.getElementById('cmpGo').onclick = function () {
            track('comparison_start', { values_count: S.candidates.length, pairs_total: total });
            showDuel();
        };
        document.getElementById('cmpRestart').onclick = function () {
            track('restart');
            api('restartTest', {}).then(function () { location.href = '/'; });
        };
    }

    /* ── 6 · Duels (tap = choose, fast rhythm) ───────────── */

    function nextUnanswered() {
        for (var i = 0; i < S.comparisons.length; i++) {
            if (!S.comparisons[i].winner_value_key) return S.comparisons[i];
        }
        return null;
    }

    function mentionsLine(key) {
        var c = candidate(key);
        return c && c.mentions && c.mentions.length ? c.mentions.join(' • ') : '';
    }

    function showDuel() {
        var c = nextUnanswered();
        if (!c) return;
        if (+c.is_tiebreak === 1) { showTiebreak(c); return; }

        var base = S.comparisons.filter(function (x) { return !+x.is_tiebreak; });
        var total = base.length;
        var done = base.filter(function (x) { return x.winner_value_key; }).length;

        function card(key) {
            var lbl = (candidate(key) || {}).label_lt || key;
            var m = mentionsLine(key);
            return '<button class="duel-card-p" data-key="' + esc(key) + '">' +
                '<div class="duel-name">' + esc(lbl) + '</div>' +
                (m ? '<div class="duel-mentions">' + esc(m) + '</div>' : '') +
                '</button>';
        }

        render(
            '<div class="duel-counter-wrap"><span class="duel-counter">' + (done + 1) + ' / ' + total + '</span></div>' +
            // the framing heading appears only on the first duel (go-live #5)
            (done === 0 ? '<h1 class="h1-p h-center" style="margin-top:.9rem">' + esc(T('duel.title')) + '</h1>' : '') +
            '<div class="duel-stack" style="margin-top:1.4rem">' +
            card(c.left_value_key) + card(c.right_value_key) +
            '</div>' +
            '<div class="duel-caption">' + esc(T('duel.caption')) + '</div>',
            true
        );

        app.querySelectorAll('.duel-card-p').forEach(function (cardEl) {
            cardEl.onclick = function () {
                app.querySelectorAll('.duel-card-p').forEach(function (x) { x.disabled = true; });
                cardEl.insertAdjacentHTML('beforeend',
                    '<span class="duel-tick">' + CHECK(16) + '</span>');
                var isLastBase = done + 1 === total;
                track('pair_choice', {
                    pair_index: c.pair_index,
                    chosen_value: (candidate(cardEl.dataset.key) || {}).label_lt || cardEl.dataset.key,
                    other_value: (candidate(cardEl.dataset.key === c.left_value_key ? c.right_value_key : c.left_value_key) || {}).label_lt,
                });
                var req = api('saveComparison', {
                    pair_index: c.pair_index,
                    winner_value_key: cardEl.dataset.key,
                }).then(function (d) {
                    if (d && d.success) c.winner_value_key = cardEl.dataset.key;
                    return d;
                });
                setTimeout(function () {
                    if (isLastBase) { finalizeFlow(req); return; }
                    req.then(function (d) {
                        if (!d || !d.success) { showDuel(); return; }
                        handleProgress(d.progress);
                    });
                }, 230);
            };
        });
    }

    /**
     * Go-live timing rules: "Štai ir viskas" beat 0.5–0.7 s, interpretation
     * requested immediately after the final tap resolves, "Skaičiuojame…"
     * state only if we wait longer than the beat, 8 s fallback shows the
     * result without interpretation blocks (they arrive by email).
     */
    function finalizeFlow(saveReq) {
        render('<div class="duel-last" style="margin-top:5rem;font-size:1.2rem">' + esc(T('duel.last')) + '</div>', true);
        var beatDone = new Promise(function (res) { setTimeout(res, 600); });
        saveReq.then(function (d) {
            if (!d || !d.success) { showDuel(); return; }
            var p = d.progress;
            if (p.state === 'tiebreak') { beatDone.then(function () { handleProgress(p); }); return; }
            if (p.state !== 'final') { beatDone.then(function () { handleProgress(p); }); return; }

            track('quiz_complete');
            S.result = { top: p.top_details, tension: p.tension, meaning: p.meaning };
            var interpReady = !!p.tension;
            var shown = false;
            var fallbackTimer = null;

            function showIt(withNote) {
                if (shown) return;
                shown = true;
                if (fallbackTimer) clearTimeout(fallbackTimer);
                showResult(withNote);
            }

            if (!interpReady) {
                // fire immediately — runs while the beat plays
                api('getInterpretation', {}).then(function (r) {
                    if (r && r.success) {
                        S.result.tension = r.tension;
                        S.result.meaning = r.meaning;
                        if (shown) { showResult(false); }   // arrived late → fill the blocks in
                        else { showIt(false); }
                    }
                });
            }

            beatDone.then(function () {
                if (interpReady || S.result.tension) { showIt(false); return; }
                // waited past the beat → animated calculating state, 8 s cap
                render(
                    '<div class="ai-screen" style="padding-top:3rem">' +
                    '<div class="ai-mountain">' +
                    '<svg width="176" height="100" viewBox="0 0 88 50" style="display:block;margin:0 auto">' +
                    '<path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="none" stroke="var(--vt-accent)" stroke-width="2.5" ' +
                    'stroke-linejoin="round" stroke-linecap="round" stroke-dasharray="340" ' +
                    'style="animation:vtDraw 3.4s ease infinite"></path></svg></div>' +
                    '<h1 class="h1-p h-center">' + esc(S.texts['calc.title'] || 'Skaičiuojame tavo rezultatą...') + '</h1>' +
                    '</div>',
                    true
                );
                fallbackTimer = setTimeout(function () { showIt(true); }, 8000);
            });
        });
    }

    /* ── 7 · Tie-break (only on a true tie) ──────────────── */

    function showTiebreak(c) {
        function card(key) {
            var lbl = (candidate(key) || {}).label_lt || key;
            return '<button class="tb-card" data-key="' + esc(key) + '">' +
                '<div class="tb-name">' + esc(lbl) + '</div></button>';
        }
        render(
            '<div class="tb-chip-wrap"><span class="chip-soft">' + esc(T('tiebreak.chip')) + '</span></div>' +
            '<h1 class="h1-p h-center">' + esc(T('tiebreak.title')) + '</h1>' +
            '<p class="sub-p sub-center" style="margin:.5rem 0 1.5rem">' + esc(T('tiebreak.sub')) + '</p>' +
            card(c.left_value_key) + card(c.right_value_key) +
            '<div class="tb-caption">' + esc(T('tiebreak.caption')) + '</div>'
        );
        app.querySelectorAll('.tb-card').forEach(function (btn) {
            btn.onclick = function () {
                app.querySelectorAll('.tb-card').forEach(function (x) { x.disabled = true; });
                track('tiebreak_choice', {
                    chosen_value: (candidate(btn.dataset.key) || {}).label_lt || btn.dataset.key,
                    other_value: (candidate(btn.dataset.key === c.left_value_key ? c.right_value_key : c.left_value_key) || {}).label_lt,
                });
                api('saveComparison', {
                    pair_index: c.pair_index,
                    winner_value_key: btn.dataset.key,
                }).then(function (d) {
                    if (!d || !d.success) { showTiebreak(c); return; }
                    c.winner_value_key = btn.dataset.key;
                    handleProgress(d.progress);
                });
            };
        });
    }

    function handleProgress(p) {
        if (!p) return;
        if (p.state === 'final') {
            track('quiz_complete');
            S.result = { top: p.top_details, tension: p.tension, meaning: p.meaning };
            if (!S.result.tension) {
                api('getInterpretation', {}).then(function (r) {
                    if (r && r.success) {
                        S.result.tension = r.tension;
                        S.result.meaning = r.meaning;
                        showResult(false);
                    }
                });
                showResult(true);
            } else {
                showResult(false);
            }
        } else if (p.state === 'tiebreak') {
            var exists = S.comparisons.some(function (x) { return +x.is_tiebreak === 1; });
            if (!exists) {
                S.comparisons.push({
                    pair_index: 99,
                    left_value_key: p.tiebreak.left,
                    right_value_key: p.tiebreak.right,
                    winner_value_key: null,
                    is_tiebreak: 1,
                });
            }
            showDuel();
        } else {
            showDuel();
        }
    }

    /* ── 8 · Result ──────────────────────────────────────── */

    function showResult(interpPending) {
        var r = S.result;
        var v1 = r.top[0] || {}, v2 = r.top[1] || {};
        track('result_view', { value_1: v1.label_lt, value_2: v2.label_lt });
        render(
            '<div class="result-chip-wrap"><span class="chip-soft">' + CHECK(11) + ' ' + esc(T('result.chip')) + '</span></div>' +
            '<h1 class="h1-p">' + esc(T('result.title')) + '</h1>' +
            '<div class="hero-card">' +
            '<div class="hero-watermark">' +
            '<svg width="96" height="55" viewBox="0 0 88 50"><path d="M3 47 L29 9 L43 25 L55 3 L85 47 Z" fill="var(--vt-on-accent)"></path></svg></div>' +
            '<div class="hero-kicker">' + esc(T('result.rank1')) + '</div>' +
            '<div class="hero-value">' + esc(String(v1.label_lt || '').toLocaleUpperCase('lt-LT')) + '</div>' +
            '</div>' +
            '<div class="second-card"><div class="sc-kicker">' + esc(T('result.rank2')) + '</div>' +
            '<div class="sc-value">' + esc(String(v2.label_lt || '').toLocaleUpperCase('lt-LT')) + '</div></div>' +
            (r.meaning ? '<div class="interp-card"><h3>' + esc(T('result.meaningTitle')) + '</h3>' +
                '<p>' + esc(r.meaning) + '</p></div>' : '') +
            (r.tension ? '<div class="interp-card"><h3>' + esc(T('result.tensionTitle')) + '</h3>' +
                '<p>' + esc(r.tension) + '</p></div>' : '') +
            (interpPending && !r.meaning
                ? '<div class="interp-card"><p>' +
                  esc(S.texts['result.interpLater'] || 'Išsamią interpretaciją atsiųsime el. paštu.') + '</p></div>'
                : '') +
            '<div class="result-next"><button class="btn-p" id="nextStepBtn">' + esc(T('result.nextCta')) + '</button>' +
            '<div class="result-caption">' + esc(T('result.nextCaption')) + '</div></div>'
        );
        document.getElementById('nextStepBtn').onclick = function () { showNextStep(); };
    }

    /* ── 9 · Kitas žingsnis (email lives here) ───────────── */

    function showNextStep() {
        track('next_step_view');
        var r = S.result || { top: [] };
        render(
            '<h1 class="h1-p next-hero">' + esc(T('next.hero')) + '</h1>' +
            '<p class="next-hero-sub">' + esc(T('next.heroSub')) + '</p>' +
            '<div class="next-section-title">' + esc(T('next.gapsTitle')) + '</div>' +
            [1, 2, 3, 4].map(function (i) {
                return '<div class="gap-row"><span class="gap-dot"><i></i></span><span>' + esc(T('next.gap' + i)) + '</span></div>';
            }).join('') +
            '<div class="next-section-title">' + esc(T('next.methodTitle')) + '</div>' +
            '<div class="sage-card">' + MOUNTAIN(30, 17, 'style="flex-shrink:0;margin-top:.2rem"') +
            '<p>' + esc(T('next.methodBody')) + '</p></div>' +
            '<div class="red-block">' +
            '<h2>' + esc(T('next.heroBig')) + '</h2>' +
            '<p>' + esc(T('next.heroBigSub')) + '</p>' +
            '<a class="btn-p on-red" id="visionBtn" href="' + esc(S.links.vision || '#') + '" target="_blank" rel="noopener">' +
            esc(T('next.visionCta')) + '</a>' +
            '</div>' +
            '<div class="card-p email-card-n">' +
            '<h3>' + esc(T('next.emailTitle')) + '</h3>' +
            '<div class="email-row-n">' +
            '<input type="email" class="input-plain" id="leadEmail" autocomplete="email" placeholder="' + esc(T('next.emailPlaceholder')) + '">' +
            '<button class="btn-p" id="leadBtn" style="width:auto;padding:.9rem 1.4rem">' + esc(T('next.emailCta')) + '</button>' +
            '</div>' +
            '<div class="field-error" id="leadError"></div>' +
            '<label class="check-line" id="leadConsentLine"><input type="checkbox" id="leadConsent">' +
            '<span>' + esc(T('next.consentRequired')) + '</span></label>' +
            '<label class="check-line" style="margin-top:.5rem"><input type="checkbox" id="leadMarketing">' +
            '<span>' + esc(T('next.consentMarketing')) + '</span></label>' +
            '<div class="field-error" id="leadConsentError">' + esc(T('error.consent')) + '</div>' +
            '<a class="link-quiet" href="/privatumas" data-policy="privacy" style="display:inline-block;margin-top:.8rem">' +
            esc(T('policy.title')) + '</a>' +
            '</div>' +
            '<div class="next-links">' +
            '<div>' + esc(T('next.deeper')) + ' <a href="' + esc(S.links.session || '#') + '" target="_blank" rel="noopener" id="sessionLink">' +
            esc(T('next.sessionLink')) + '</a></div>' +
            '<div>' + esc(T('next.moreInsights')) + ' <a href="' + esc(S.links.facebook || '#') + '" target="_blank" rel="noopener" id="fbLink">' +
            esc(T('next.followFb')) + '</a></div>' +
            '</div>' +
            '<div class="next-footer">' + esc(T('next.footer')) +
            '<div class="mark">' + MOUNTAIN(24, 14) + '</div>' + esc(T('next.tagline')) + '</div>'
        );

        document.getElementById('visionBtn').addEventListener('click', function () { track('vision_method_click'); });
        document.getElementById('sessionLink').addEventListener('click', function () { track('session_info_click'); });
        document.getElementById('fbLink').addEventListener('click', function () { track('follow_click', { source: 'next_step' }); });

        document.getElementById('leadBtn').onclick = function () {
            var emailEl = document.getElementById('leadEmail');
            var err = document.getElementById('leadError');
            var consentEl = document.getElementById('leadConsent');
            var consentErr = document.getElementById('leadConsentError');
            var line = document.getElementById('leadConsentLine');
            err.classList.remove('show');
            consentErr.classList.remove('show');
            emailEl.classList.remove('invalid');
            line.classList.remove('invalid');

            var email = emailEl.value.trim();
            var bad = false;
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                err.textContent = T('error.email');
                err.classList.add('show');
                emailEl.classList.add('invalid');
                track('submit_error', { reason: 'invalid_email' });
                bad = true;
            }
            if (!consentEl.checked) {
                consentErr.classList.add('show');
                line.classList.add('invalid');
                if (!bad) track('submit_error', { reason: 'no_consent' });
                bad = true;
            }
            if (bad) return;

            var marketing = document.getElementById('leadMarketing').checked;
            var btn = this;
            btn.disabled = true;
            api('saveLead', { email: email, consent: true, marketing_opt_in: marketing }).then(function (d) {
                if (!d.success) {
                    btn.disabled = false;
                    err.textContent = d.message || T('common.errorGeneric');
                    err.classList.add('show');
                    track('submit_error', { reason: 'api_error' });
                    return;
                }
                var v1 = (S.result && S.result.top[0]) || {};
                var v2 = (S.result && S.result.top[1]) || {};
                track('email_submit', { value_1: v1.label_lt, value_2: v2.label_lt, marketing_opt_in: marketing });
                if (window.fbq) fbq('track', 'Lead');
                S.sentEmail = email;
                showSent();
            });
        };
    }

    /* ── 10 · Sent ───────────────────────────────────────── */

    function showSent() {
        render(
            '<div class="sent-screen">' +
            '<div class="sent-mark">' + MOUNTAIN(94, 54) +
            '<span class="tick-badge">' + CHECK(14, 'var(--vt-on-accent)') + '</span></div>' +
            '<h1 class="h1-p h-center sent-line">' + esc(T('sent.title')) + '</h1>' +
            '<p class="sub-p sub-center sent-line d1" style="margin-top:.6rem">' +
            (S.sentEmail
                ? esc(T('sent.to', { email: '' })).replace('{email}', '') + '<span class="sent-email">' + esc(S.sentEmail) + '</span>'
                : esc(T('sent.toFallback'))) + '</p>' +
            '<p class="sub-p sub-center sent-line d2" style="margin-top:.4rem">' + esc(T('sent.thanks')) + '</p>' +
            '<hr class="sent-hr">' +
            '<div class="sent-follow"><div class="f-title">' + esc(T('sent.follow')) + '</div>' +
            '<a href="' + esc(S.links.facebook || '#') + '" target="_blank" rel="noopener" id="sentFb">' + esc(T('sent.followLink')) + '</a></div>' +
            '<div class="sent-spam">' + esc(T('sent.spam')) + '</div>' +
            '<button class="btn-p ghost" id="againBtn" style="max-width:280px">' + esc(T('sent.again')) + '</button>' +
            '</div>'
        );
        document.getElementById('sentFb').addEventListener('click', function () { track('follow_click', { source: 'sent' }); });
        document.getElementById('againBtn').onclick = function () {
            track('restart');
            api('restartTest', {}).then(function () { location.href = '/'; });
        };
    }

    /* ── Resume ──────────────────────────────────────────── */

    function resume() {
        var s = S.session;
        if (!s) { showIntro(); return; }

        S.answers = {};
        (s.answers || []).forEach(function (a) {
            var list = S.answers[a.question_key] = S.answers[a.question_key] || [];
            list[a.answer_index] = a.answer_text;
        });
        for (var k in S.answers) {
            S.answers[k] = S.answers[k].filter(function (v) { return v !== undefined; });
        }
        S.candidates = s.candidates || [];
        S.comparisons = s.comparisons || [];

        switch (s.status) {
            case 'result_ready':
            case 'email_captured':
                if (s.result) {
                    S.result = s.result;
                    if (s.status === 'email_captured') { showSent(); } else { showResult(); }
                    return;
                }
                showIntro(); return;
            case 'comparing': {
                var answered = S.comparisons.some(function (c) { return c.winner_value_key; });
                if (nextUnanswered()) {
                    if (answered) { showDuel(); } else { showComparison(); }
                } else { showComparison(); }
                return;
            }
            case 'answering': {
                // land on the first question without answers (session restore)
                var idx = 0;
                for (var i = 0; i < S.questions.length; i++) {
                    idx = i;
                    if (!(S.answers[S.questions[i].question_key] || []).length) break;
                }
                S.qIndex = idx;
                showQuestion(); return;
            }
            case 'consented':
                S.qIndex = 0;
                showQuestion(); return;
            default:
                showIntro();
        }
    }

    /* ── Boot ────────────────────────────────────────────── */

    attribution(); // persist first touch
    api('getTestBootstrap', null, 'GET').then(function (d) {
        if (!d.success) {
            app.innerHTML = '<div class="test-loading">' + esc(T('common.errorGeneric')) + '</div>';
            return;
        }
        S.texts = d.texts;
        S.questions = d.questions;
        S.links = d.links || {};
        S.session = d.session;
        track('view');
        resume();
    });
})();
