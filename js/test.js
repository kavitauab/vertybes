/* Vertybių testas — public test flow, matched to the design PDFs.
 *
 * Screens: intro → consent (2/3) → cookies (3/3) → questions ×4 →
 *          AI loading → review (+ value picker) → compare intro →
 *          duels ×15 (+ tie-break) → result → sent.
 * Resume via getTestBootstrap; answers autosave per question.
 */
(function () {
    'use strict';

    var app = document.getElementById('app');

    var S = {
        texts: {}, questions: [], catalog: [], bookingUrl: '',
        session: null,
        consentChecked: false,
        qIndex: 0,
        answers: {},          // question_key -> [strings]
        reviewAnswers: [],
        statements: {},       // value_key -> first-person statement
        quotes: {},           // value_key -> [user answers]
        top: [],              // value detail rows
        comparisons: [],
        result: null,
        pickerFor: null,
        pickerSelected: null,
        duelBackTo: null,
        slowTimer: null,
    };

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
    function render(html) {
        if (S.slowTimer) { clearTimeout(S.slowTimer); S.slowTimer = null; }
        app.innerHTML = '<div class="screen">' + html + '</div>';
        window.scrollTo({ top: 0 });
    }
    function byKey(key) {
        for (var i = 0; i < S.catalog.length; i++) {
            if (S.catalog[i].value_key === key) return S.catalog[i];
        }
        return null;
    }
    function upper(s) { return String(s || '').toLocaleUpperCase('lt-LT'); }

    /* ── Icons ───────────────────────────────────────────── */
    var I = {
        sparkle: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M10 2l1.8 5.4L17 9l-5.2 1.6L10 16l-1.8-5.4L3 9l5.2-1.6L10 2zm8 8l1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3zm-2 8l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7.7-2z"/></svg>',
        check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="13" height="13"><polyline points="20 6 9 17 4 12"/></svg>',
        checkBig: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" width="34" height="34"><polyline points="20 6 9 17 4 12"/></svg>',
        clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>',
        doc: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M9 9h6M9 13h6M9 17h4"/></svg>',
        leaf: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 3c1.5 2.5 1.5 5.5 0 8-1.5-2.5-1.5-5.5 0-8zM6 8c2.9.3 5.2 2 6 5-2.9-.3-5.2-2-6-5zm12 0c-.8 3-3.1 4.7-6 5 .8-3 3.1-4.7 6-5z"/></svg>',
        hourglass: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="22" height="22"><path d="M6 3h12M6 21h12M8 3v4l4 5 4-5V3M8 21v-4l4-5 4 5v4"/></svg>',
        search: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>',
        plusCircle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>',
        pencil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="13" height="13"><path d="M17 3l4 4L8 20l-5 1 1-5L17 3z"/></svg>',
        warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3L2 20h20L12 3z"/><path d="M12 10v4M12 17.5v.5"/></svg>',
        plane: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3L10 14M21 3l-7 18-4-7-7-4 18-7z"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15" style="flex-shrink:0;margin-top:.15rem"><circle cx="12" cy="12" r="9"/><path d="M12 8v.5M12 11v5" stroke-linecap="round"/></svg>',
        chevron: '&#8249;',
        arrow: '&#8594;',
    };

    /* ── Shared partials ─────────────────────────────────── */

    function stepHead(n) {
        var pct = Math.round(n / 3 * 100);
        return '<div class="step-head">' +
            '<div class="step-dot">' + n + '</div>' +
            '<div class="step-label">' + esc(T('steps.of')) + '</div>' +
            '<div class="step-line"><i style="width:' + pct + '%"></i></div>' +
            '</div>';
    }

    function progressHead(label, pct, backFn) {
        window.__backFn = backFn || null;
        return '<div class="progress-head">' +
            '<div class="progress-row">' +
            (backFn ? '<button class="back-btn" onclick="__backFn()" aria-label="' + esc(T('common.back')) + '">' + I.chevron + '</button>' : '') +
            '<div class="progress-meta">' +
            '<div class="progress-label-p">' + esc(label) + '</div>' +
            '<div class="progress-track-p"><div class="progress-fill-p" style="width:' + pct + '%"></div></div>' +
            '</div></div><hr class="progress-hr"></div>';
    }

    /* ── Intro (step 1) ──────────────────────────────────── */

    function showIntro() {
        render(
            '<div class="intro-icon">' + I.sparkle + '<span class="dot"></span></div>' +
            '<h1 class="h-display h-hero">' + esc(T('intro.hero')) + '</h1>' +
            '<p class="sub-p sub-center intro-sub">' + esc(T('intro.sub')) + '</p>' +
            '<div class="card-p intro-checklist">' +
            [1, 2, 3].map(function (i) {
                return '<div class="intro-check"><span class="tick">' + I.check + '</span>' +
                    '<span>' + esc(T('intro.bullet' + i)) + '</span></div>';
            }).join('') +
            '</div>' +
            '<button class="btn-p" id="startBtn">' + esc(T('intro.cta')) + '</button>'
        );
        document.getElementById('startBtn').onclick = function () {
            if (document.cookie.indexOf('consent_given=1') !== -1) { startSession(); return; }
            showConsent();
        };
    }

    /* ── Consent (step 2) ────────────────────────────────── */

    function showConsent(withError) {
        render(
            stepHead(2) +
            '<h1 class="h-display h-screen">' + esc(T('consent.title')) + '</h1>' +
            '<div class="card-p consent-card">' +
            '<div class="icon-chip">' + I.clock + '</div>' +
            '<p>' + esc(T('consent.aiInfo')) + '</p>' +
            '</div>' +
            '<p class="policy-link-line">' + esc(T('consent.linkIntro')) + '<br>' +
            '<a href="/privatumas" data-policy="privacy">' + esc(T('consent.linkText')) + '</a>.</p>' +
            '<label class="check-line' + (withError ? ' invalid' : '') + '" id="consentLine">' +
            '<input type="checkbox" id="consentCheck"' + (S.consentChecked ? ' checked' : '') + '>' +
            '<span>' + esc(T('consent.checkbox')) + '</span></label>' +
            '<div class="field-error' + (withError ? ' show' : '') + '" id="consentError">' +
            I.info + '<span>' + esc(T('consent.error')) + '</span></div>' +
            '<div style="margin-top:1.5rem"><button class="btn-p" id="consentBtn">' + esc(T('common.continue')) + '</button></div>'
        );
        document.getElementById('consentCheck').onchange = function () {
            S.consentChecked = this.checked;
            if (this.checked) {
                document.getElementById('consentLine').classList.remove('invalid');
                document.getElementById('consentError').classList.remove('show');
            }
        };
        document.getElementById('consentBtn').onclick = function () {
            if (!S.consentChecked) { showConsent(true); return; }
            showCookies();
        };
    }

    /* ── Cookies (step 3) ────────────────────────────────── */

    function showCookies(declined) {
        render(
            stepHead(3) +
            '<h1 class="h-display h-screen">' + esc(T('cookies.title')) + '</h1>' +
            '<div class="card-p consent-card">' +
            '<div class="icon-chip">' + I.doc + '</div>' +
            '<p>' + esc(T('cookies.body')) + '</p>' +
            '</div>' +
            '<p class="policy-link-line"><a href="/slapukai" data-policy="cookies">' + esc(T('cookies.popup.title')) + '</a></p>' +
            (declined ? '<div class="error-banner" style="margin-bottom:1rem">' + I.info + '<span>' + esc(T('cookies.declined')) + '</span></div>' : '') +
            '<button class="btn-p" id="acceptBtn">' + esc(T('cookies.accept')) + '</button>' +
            '<div style="margin-top:.8rem"><button class="btn-p ghost quiet" id="declineBtn">' + esc(T('cookies.decline')) + '</button></div>'
        );
        document.getElementById('acceptBtn').onclick = function () {
            this.disabled = true;
            startSession();
        };
        document.getElementById('declineBtn').onclick = function () { showCookies(true); };
    }

    function startSession() {
        api('startTest', { privacy_accepted: true, cookies_accepted: true }).then(function (d) {
            if (!d.success) { showCookies(true); return; }
            document.cookie = 'consent_given=1; path=/; max-age=31536000; samesite=lax';
            S.qIndex = 0;
            showQuestion();
        });
    }

    /* ── Questions ───────────────────────────────────────── */

    var autosaveTimer = null;

    function showQuestion(errorBanner) {
        var q = S.questions[S.qIndex];
        if (!q) { startAnalysis(); return; }
        var total = S.questions.length;
        var cur = S.qIndex + 1;
        var saved = S.answers[q.question_key] || [];
        var slots = Math.max(2, saved.length);
        var hasError = !!errorBanner;

        var fields = '';
        for (var i = 0; i < slots; i++) {
            var ph = (q.placeholders && q.placeholders[i]) ? q.placeholders[i]
                   : (q.placeholders && q.placeholders.length ? q.placeholders[q.placeholders.length - 1] : '');
            fields += '<div class="answer-row-p">' +
                '<div class="field-card' + (hasError && i < 2 ? ' invalid' : '') + '">' +
                '<div class="field-head">' +
                '<span class="field-label">' + esc(T('questions.answerLabel', { n: i + 1 })) + '</span>' +
                (hasError && i < 2 ? '<span class="field-required">' + esc(T('questions.required')) + '</span>' : '') +
                '</div>' +
                '<input type="text" maxlength="500" data-idx="' + i + '"' +
                ' placeholder="' + esc(ph) + '" value="' + esc(saved[i] || '') + '">' +
                '</div>' +
                (i >= 2 ? '<button class="answer-remove-p" data-remove="' + i + '" title="Pašalinti">&times;</button>' : '') +
                '</div>';
        }

        render(
            progressHead(T('questions.progress', { current: cur, total: total }), Math.round(cur / total * 100),
                function () {
                    collectCurrent();
                    if (S.qIndex === 0) { showIntro(); } else { S.qIndex--; showQuestion(); }
                }) +
            '<h1 class="h-display h-screen" style="margin-top:1.4rem">' + esc(q.text) + '</h1>' +
            '<p class="q-help">' + esc(q.hint || '') + '</p>' +
            (hasError ? '<div class="error-banner q-error">' + I.info + '<span>' + esc(errorBanner) + '</span></div>' : '') +
            '<div class="answers-stack" id="answerStack">' + fields + '</div>' +
            (slots < (+q.max_answers || 6)
                ? '<button class="btn-sand" id="addAnswer"><span class="plus">+</span>' + esc(T('questions.addAnswer')) + '</button>'
                : '') +
            '<div style="margin-top:1.5rem"><button class="btn-p" id="nextBtn">' + esc(T('common.continue')) + '</button></div>' +
            '<div class="autosave-note">' + esc(T('questions.autosave')) + '</div>'
        );

        var stack = document.getElementById('answerStack');
        stack.addEventListener('input', function () {
            collectCurrent();
            if (autosaveTimer) clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(function () { autosave(q.question_key); }, 1800);
        });
        stack.querySelectorAll('[data-remove]').forEach(function (b) {
            b.onclick = function () {
                collectCurrent();
                S.answers[q.question_key].splice(+b.dataset.remove, 1);
                showQuestion();
            };
        });
        var add = document.getElementById('addAnswer');
        if (add) add.onclick = function () {
            collectCurrent();
            var list = S.answers[q.question_key] = S.answers[q.question_key] || [];
            while (list.length < slots) list.push('');
            list.push('');
            showQuestion();
            var inputs = document.querySelectorAll('#answerStack input');
            inputs[inputs.length - 1].focus();
        };
        document.getElementById('nextBtn').onclick = function () {
            collectCurrent();
            var vals = (S.answers[q.question_key] || []).filter(function (v) { return v.trim() !== ''; });
            if (!vals.length) { showQuestion(T('questions.error')); return; }
            var btn = this;
            btn.disabled = true;
            api('saveQuestionAnswers', { question_key: q.question_key, answers: vals }).then(function (d) {
                if (!d.success) { btn.disabled = false; showQuestion(d.message || T('common.errorGeneric')); return; }
                S.answers[q.question_key] = vals;
                S.qIndex++;
                showQuestion();
            });
        };

        function collectCurrent() {
            var inputs = document.querySelectorAll('#answerStack input');
            var list = [];
            inputs.forEach(function (inp) { list.push(inp.value); });
            S.answers[q.question_key] = list;
        }
    }

    function autosave(qKey) {
        var vals = (S.answers[qKey] || []).filter(function (v) { return v.trim() !== ''; });
        if (vals.length) api('saveQuestionAnswers', { question_key: qKey, answers: vals });
    }

    /* ── AI loading ──────────────────────────────────────── */

    function showAiLoading(slow) {
        render(
            '<div class="ai-screen">' +
            '<div class="ai-mark"><div class="arc"></div><div class="leaf">' + I.leaf + '</div></div>' +
            '<h1 class="h-display h-screen h-center">' + esc(T('loading.title')) + '</h1>' +
            '<div class="ai-dash"></div>' +
            '<p class="sub-p sub-center">' + esc(T('loading.sub')) + '</p>' +
            (slow
                ? '<div class="card-p slow-card">' +
                  '<div class="slow-head">' + I.hourglass + '<span>' + esc(T('loading.slow.title')) + '</span></div>' +
                  '<p>' + esc(T('loading.slow.body')) + '</p></div>' +
                  '<div class="slow-actions">' +
                  '<button class="btn-p" id="waitBtn">' + esc(T('loading.wait')) + '</button>' +
                  '<button class="btn-p ghost quiet" id="retryBtn">' + esc(T('loading.retry')) + '</button></div>'
                : '<div class="ai-chip"><span class="pulse"></span>' + esc(T('loading.chip')) + '</div>') +
            '</div>'
        );
        if (slow) {
            document.getElementById('waitBtn').onclick = function () { showAiLoading(false); armSlowTimer(); };
            document.getElementById('retryBtn').onclick = function () { startAnalysis(); };
        }
    }

    function armSlowTimer() {
        if (S.slowTimer) clearTimeout(S.slowTimer);
        S.slowTimer = setTimeout(function () { showAiLoading(true); }, 10000);
    }

    function startAnalysis() {
        showAiLoading(false);
        armSlowTimer();
        api('getSuggestions', {}).then(function (d) {
            if (S.slowTimer) { clearTimeout(S.slowTimer); S.slowTimer = null; }
            if (!d.success) { showAiLoading(true); return; }
            S.reviewAnswers = d.answers;
            showReview();
        });
    }

    /* ── Review ──────────────────────────────────────────── */

    function isUncertain(a) {
        return a.confirmed_value_key &&
            a.confidence !== null && a.confidence !== undefined &&
            +a.confidence < 0.6 && a.suggested_value_key === a.confirmed_value_key;
    }

    function showReview(errorBanner) {
        var byQuestion = {};
        S.reviewAnswers.forEach(function (a) {
            (byQuestion[a.question_key] = byQuestion[a.question_key] || []).push(a);
        });

        var body = '';
        S.questions.forEach(function (q) {
            var items = byQuestion[q.question_key];
            if (!items) return;
            body += '<div class="review-section"><span>' + esc(upper(q.topic_label || q.text)) + '</span></div>';
            items.forEach(function (a) {
                var v = a.confirmed_value_key ? byKey(a.confirmed_value_key) : null;
                var uncertain = isUncertain(a);
                var i = S.reviewAnswers.indexOf(a);
                body += '<div class="review-card">' +
                    '<div class="mini-label">' + esc(T('review.answerLabel')) + '</div>' +
                    '<div class="answer-quote">„' + esc(a.answer_text) + '“</div>' +
                    (uncertain ? '<div class="uncertain-chip">' + I.info + esc(T('review.uncertain')) + '</div>' : '') +
                    '<div class="mini-label">' + esc(T('review.valueLabel')) + '</div>' +
                    '<div class="value-row">' +
                    '<span class="value-name">' + (v ? esc(upper(v.label_lt)) : '—') + '</span>' +
                    '<button class="change-chip" data-i="' + i + '">' + I.pencil + esc(T('review.change')) + '</button>' +
                    '</div>' +
                    '<div class="value-meaning' + (uncertain ? ' uncertain' : '') + '">' +
                    esc(uncertain ? T('review.uncertainBody') : (v ? v.meaning_lt : '')) + '</div>' +
                    '</div>';
            });
        });

        render(
            '<h1 class="h-display h-screen" style="margin-top:.6rem">' + esc(T('review.title')) + '</h1>' +
            '<p class="q-help">' + esc(T('review.sub')) + '</p>' +
            (errorBanner ? '<div class="error-banner q-error">' + I.info + '<span>' + esc(errorBanner) + '</span></div>' : '') +
            body +
            '<div style="margin-top:1.4rem"><button class="btn-p" id="confirmBtn">' + esc(T('common.continue')) + '</button></div>'
        );

        app.querySelectorAll('.change-chip').forEach(function (chip) {
            chip.onclick = function () { openPicker(+chip.dataset.i); };
        });
        document.getElementById('confirmBtn').onclick = confirmValues;
    }

    function confirmValues() {
        var missing = S.reviewAnswers.some(function (a) { return !a.confirmed_value_key; });
        if (missing) { showReview(T('review.uncertainBody')); return; }
        var btn = document.getElementById('confirmBtn');
        btn.disabled = true;
        btn.textContent = T('loading.chip');
        api('confirmValues', {
            confirmations: S.reviewAnswers.map(function (a) {
                return { answer_id: a.id, value_key: a.confirmed_value_key };
            }),
        }).then(function (d) {
            if (!d.success) { showReview(d.message || T('common.errorGeneric')); return; }
            if (d.needs_more_answers) {
                S.qIndex = 0;
                showQuestion(T('questions.needMore'));
                return;
            }
            S.top = d.top;
            S.statements = d.statements || {};
            S.quotes = d.quotes || {};
            S.comparisons = d.comparisons;
            showCompareIntro();
        });
    }

    /* ── Value picker ────────────────────────────────────── */

    function openPicker(i) {
        S.pickerFor = i;
        S.pickerSelected = S.reviewAnswers[i].confirmed_value_key || null;
        showPicker('');
    }

    function showPicker(query) {
        var q = (query || '').trim().toLowerCase();
        var list;
        var title;
        if (q === '') {
            list = S.catalog.filter(function (v) { return +v.is_core === 1; });
            title = T('picker.coreTitle');
        } else {
            list = S.catalog.filter(function (v) {
                return v.label_lt.toLowerCase().indexOf(q) !== -1 ||
                       (v.synonyms_lt || '').toLowerCase().indexOf(q) !== -1;
            }).slice(0, 30);
            title = T('picker.allTitle');
        }
        // keep the current selection visible even if it's not in the list
        if (S.pickerSelected && !list.some(function (v) { return v.value_key === S.pickerSelected; })) {
            var sel = byKey(S.pickerSelected);
            if (sel) list = [sel].concat(list);
        }

        var grid = list.map(function (v) {
            return '<button class="value-cell' + (v.value_key === S.pickerSelected ? ' selected' : '') + '"' +
                ' data-key="' + esc(v.value_key) + '">' + esc(v.label_lt) + '</button>';
        }).join('');

        render(
            progressHead(T('picker.title'), 100, function () { showReview(); }) +
            '<div class="picker-screen">' +
            '<h1 class="h-display h-screen h-center" style="margin-top:1.2rem">' + esc(T('picker.title')) + '</h1>' +
            '<div class="picker-search-wrap">' + I.search +
            '<input type="text" class="picker-search-p" id="pickerSearch" value="' + esc(query || '') + '"' +
            ' placeholder="' + esc(T('picker.search')) + '"></div>' +
            '<div class="picker-head-row"><h2>' + esc(title) + '</h2>' +
            '<span class="chip-plain">' + esc(T('picker.requiredChip')) + '</span></div>' +
            '<div class="value-grid">' + grid + '</div>' +
            '<div class="card-p custom-card">' +
            '<div class="custom-head">' + I.plusCircle + esc(T('picker.customTitle')) + '</div>' +
            '<input type="text" class="input-plain" id="customInput" maxlength="60"' +
            ' placeholder="' + esc(T('picker.customPlaceholder')) + '">' +
            '</div>' +
            '<button class="btn-p" id="pickerSave">' + esc(T('picker.save')) + '</button>' +
            '</div>'
        );

        var searchEl = document.getElementById('pickerSearch');
        var searchTimer = null;
        searchEl.addEventListener('input', function () {
            var val = this.value;
            if (searchTimer) clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                var pos = searchEl.selectionStart;
                showPicker(val);
                var el = document.getElementById('pickerSearch');
                el.focus();
                try { el.setSelectionRange(pos, pos); } catch (e) {}
            }, 250);
        });
        app.querySelectorAll('.value-cell').forEach(function (cell) {
            cell.onclick = function () {
                S.pickerSelected = cell.dataset.key;
                app.querySelectorAll('.value-cell').forEach(function (c) { c.classList.remove('selected'); });
                cell.classList.add('selected');
            };
        });
        document.getElementById('pickerSave').onclick = function () {
            var btn = this;
            var custom = document.getElementById('customInput').value.trim();
            var apply = function (key) {
                if (S.pickerFor !== null && key) {
                    S.reviewAnswers[S.pickerFor].confirmed_value_key = key;
                    S.reviewAnswers[S.pickerFor].source = 'user';
                    S.reviewAnswers[S.pickerFor].confidence = 1;
                }
                showReview();
            };
            if (custom !== '') {
                btn.disabled = true;
                api('addCustomValue', { label: custom }).then(function (d) {
                    if (!d.success) { btn.disabled = false; return; }
                    if (!byKey(d.value.value_key)) S.catalog.push(d.value);
                    apply(d.value.value_key);
                });
            } else {
                apply(S.pickerSelected);
            }
        };
    }

    /* ── Compare intro ───────────────────────────────────── */

    function showCompareIntro() {
        var cards = S.top.map(function (v) {
            var quotes = (S.quotes[v.value_key] || []).slice(0, 2).map(function (t) {
                return '<div class="ci-quote">„' + esc(t) + '“</div>';
            }).join('');
            return '<div class="ci-card"><div class="ci-name">' + esc(upper(v.label_lt)) + '</div>' + quotes + '</div>';
        }).join('');

        render(
            '<h1 class="h-display h-screen" style="margin-top:.6rem">' +
            esc(T('compare.introTitle', { n: S.top.length })) + '</h1>' +
            '<p class="q-help">' + esc(T('compare.introSub')) + '</p>' +
            '<div class="ci-grid">' + cards + '</div>' +
            '<button class="btn-p" id="goBtn">' + esc(T('compare.introCta')) + '</button>' +
            '<div class="btn-caption">' + esc(T('compare.introCaption')) + '</div>'
        );
        document.getElementById('goBtn').onclick = function () { showDuel(); };
    }

    /* ── Duels ───────────────────────────────────────────── */

    function nextUnanswered() {
        for (var i = 0; i < S.comparisons.length; i++) {
            if (!S.comparisons[i].winner_value_key) return S.comparisons[i];
        }
        return null;
    }

    function duelQuote(key) {
        var st = S.statements[key];
        if (st) return st;
        var v = byKey(key);
        return v ? (v.meaning_lt || v.label_lt) : key;
    }

    function showDuel(pairIndex) {
        var c = null;
        if (pairIndex) {
            S.comparisons.forEach(function (x) { if (+x.pair_index === +pairIndex) c = x; });
        } else {
            c = nextUnanswered();
        }
        if (!c) return;
        if (+c.is_tiebreak === 1) { showTiebreak(c); return; }

        var base = S.comparisons.filter(function (x) { return !+x.is_tiebreak; });
        var total = base.length;
        var answered = base.filter(function (x) { return x.winner_value_key; }).length;
        var displayNo = Math.min(answered + 1, total);
        var idxInBase = base.indexOf(c);
        var prev = idxInBase > 0 ? base[idxInBase - 1] : null;

        function cardHtml(key) {
            var v = byKey(key);
            return '<button class="duel-card-p" data-key="' + esc(key) + '">' +
                '<div class="duel-name">' + esc(upper(v ? v.label_lt : key)) + '</div>' +
                '<div class="duel-quote">„' + esc(duelQuote(key)) + '“</div>' +
                '</button>';
        }

        render(
            progressHead(T('compare.progress', { current: displayNo, total: total }),
                Math.round(answered / total * 100),
                prev ? function () { showDuel(prev.pair_index); } : null) +
            '<h1 class="h-display h-screen h-center" style="margin-top:1.6rem">' + esc(T('compare.title')) + '</h1>' +
            '<p class="sub-p sub-center" style="margin:.5rem 0 1.7rem">' + esc(T('compare.help')) + '</p>' +
            '<div class="duel-stack">' +
            cardHtml(c.left_value_key) +
            '<div class="or-divider">' + esc(T('compare.or')) + '</div>' +
            cardHtml(c.right_value_key) +
            '</div>' +
            '<div class="duel-caption">' + esc(T('compare.caption')) + '</div>'
        );

        app.querySelectorAll('.duel-card-p').forEach(function (card) {
            card.onclick = function () {
                app.querySelectorAll('.duel-card-p').forEach(function (x) { x.disabled = true; });
                card.classList.add('chosen');
                var req = api('saveComparison', {
                    pair_index: c.pair_index,
                    winner_value_key: card.dataset.key,
                }).then(function (d) {
                    if (d && d.success) c.winner_value_key = card.dataset.key;
                    return d;
                });
                afterChoice(c, req);
            };
        });
    }

    function showTiebreak(c) {
        function cardHtml(key) {
            var v = byKey(key);
            return '<div class="tb-card">' +
                '<div class="tb-name">' + esc(upper(v ? v.label_lt : key)) + '</div>' +
                '<button class="btn-p" data-key="' + esc(key) + '">' + esc(T('tiebreak.choose')) + '</button>' +
                '</div>';
        }
        render(
            '<div class="tb-chip-wrap"><span class="chip-soft">' + esc(T('tiebreak.chip')) + '</span></div>' +
            '<h1 class="h-display h-screen h-center">' + esc(T('tiebreak.title')) + '</h1>' +
            '<p class="sub-p sub-center" style="margin:.5rem 0 1.7rem">' + esc(T('tiebreak.sub')) + '</p>' +
            cardHtml(c.left_value_key) +
            '<div class="or-divider">' + esc(T('compare.or')) + '</div>' +
            cardHtml(c.right_value_key)
        );
        app.querySelectorAll('.tb-card .btn-p').forEach(function (btn) {
            btn.onclick = function () {
                app.querySelectorAll('.tb-card .btn-p').forEach(function (x) { x.disabled = true; });
                var req = api('saveComparison', {
                    pair_index: c.pair_index,
                    winner_value_key: btn.dataset.key,
                }).then(function (d) {
                    if (d && d.success) c.winner_value_key = btn.dataset.key;
                    return d;
                });
                afterChoice(c, req);
            };
        });
    }

    function showResultLoading() {
        render(
            '<div class="ai-screen">' +
            '<div class="ai-mark"><div class="arc"></div><div class="leaf">' + I.leaf + '</div></div>' +
            '<h1 class="h-display h-screen h-center">' + esc(T('loading.result.title')) + '</h1>' +
            '<div class="ai-dash"></div>' +
            '<p class="sub-p sub-center">' + esc(T('loading.result.sub')) + '</p>' +
            '<div class="ai-chip"><span class="pulse"></span>' + esc(T('loading.result.chip')) + '</div>' +
            '</div>'
        );
    }

    /**
     * Show the chosen state briefly; if this was the last open duel the server
     * also generates the result texts (a few seconds) — switch to a loading
     * screen instead of appearing stuck.
     */
    function afterChoice(c, request) {
        var othersOpen = S.comparisons.some(function (x) {
            return x !== c && !x.winner_value_key;
        });
        var settled = false;
        if (!othersOpen) {
            setTimeout(function () { if (!settled) showResultLoading(); }, 400);
        }
        request.then(function (d) {
            settled = true;
            if (!d || !d.success) { showDuel(c.pair_index); return; }
            setTimeout(function () { handleProgress(d.progress); }, othersOpen ? 350 : 0);
        });
    }

    function handleProgress(p) {
        if (!p) return;
        if (p.state === 'final') {
            S.result = { top: p.top_details, tension: p.tension, meaning: p.meaning };
            showResult(false);
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

    /* ── Result ──────────────────────────────────────────── */

    function showResult(sent) {
        var r = S.result;
        var cards = r.top.map(function (v, i) {
            return '<div class="result-value-card">' +
                '<div class="rv-kicker">' + esc(T('result.rank' + (i + 1))) + '</div>' +
                '<div class="rv-name">' + esc(upper(v.label_lt)) + '</div>' +
                '</div>';
        }).join('');

        var emailBlock = sent
            ? '<div class="result-sent-note">' + I.check + ' ' + esc(T('sent.title')) + ' — ' + esc(T('sent.sub')) + '</div>'
            : '<div class="card-p email-card">' +
              '<div class="e-title">' + esc(T('result.emailTitle')) + '</div>' +
              '<div class="e-sub">' + esc(T('result.emailSub')) + '</div>' +
              '<div class="e-label" id="emailLabel">' + esc(T('result.emailLabel')) + '</div>' +
              '<input type="email" class="input-plain" id="resEmail" autocomplete="email"' +
              ' placeholder="' + esc(T('result.emailPlaceholder')) + '">' +
              '<div class="field-error" id="emailError">' + I.info + '<span></span></div>' +
              '<label class="check-line" id="consentEmailLine">' +
              '<input type="checkbox" id="consentEmail"><span>' + esc(T('result.emailConsent')) + '</span></label>' +
              '<div class="field-error" id="consentError">' + I.info + '<span>' + esc(T('result.errorConsent')) + '</span></div>' +
              '<button class="btn-p upper" id="sendBtn">' + esc(T('result.emailSend')) + '</button>' +
              '<a class="privacy-link" href="/privatumas" data-policy="privacy">' + esc(T('result.privacyLink')) + '</a>' +
              '</div>';

        render(
            '<h1 class="h-display h-screen" style="margin-top:.6rem">' + esc(T('result.title')) + '</h1>' +
            '<p class="q-help" style="margin-bottom:0">' + esc(T('result.sub')) + '</p>' +
            '<div class="result-dash"></div>' +
            cards +
            (r.tension ? '<div class="tension-card">' +
                '<div class="t-head"><span class="t-icon">' + I.warn + '</span>' +
                '<span class="t-title">' + esc(T('result.tensionTitle')) + '</span></div>' +
                '<p>' + esc(r.tension) + '</p></div>' : '') +
            (r.meaning ? '<div class="meaning-card">' +
                '<div class="m-title">' + esc(T('result.meaningTitle')) + '</div>' +
                '<p>' + esc(r.meaning) + '</p></div>' : '') +
            (S.bookingUrl ? '<a class="btn-p ghost upper" href="' + esc(S.bookingUrl) + '" target="_blank" rel="noopener">' +
                esc(T('result.cta')) + ' ' + I.arrow + '</a>' : '') +
            emailBlock
        );

        if (sent) return;

        document.getElementById('sendBtn').onclick = function () {
            var emailEl = document.getElementById('resEmail');
            var emailErr = document.getElementById('emailError');
            var consentEl = document.getElementById('consentEmail');
            var consentErr = document.getElementById('consentError');
            var label = document.getElementById('emailLabel');
            var line = document.getElementById('consentEmailLine');
            emailErr.classList.remove('show');
            consentErr.classList.remove('show');
            emailEl.classList.remove('invalid');
            label.classList.remove('invalid');
            line.classList.remove('invalid');

            var email = emailEl.value.trim();
            var bad = false;
            if (email === '') {
                emailErr.querySelector('span').textContent = T('result.errorEmpty');
                emailErr.classList.add('show');
                emailEl.classList.add('invalid');
                label.classList.add('invalid');
                bad = true;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                emailErr.querySelector('span').textContent = T('result.errorInvalid');
                emailErr.classList.add('show');
                emailEl.classList.add('invalid');
                label.classList.add('invalid');
                bad = true;
            }
            if (!consentEl.checked) {
                consentErr.classList.add('show');
                line.classList.add('invalid');
                bad = true;
            }
            if (bad) return;

            var btn = this;
            btn.disabled = true;
            api('saveResultEmail', { email: email, consent: true }).then(function (d) {
                if (!d.success) {
                    btn.disabled = false;
                    emailErr.querySelector('span').textContent = d.message || T('common.errorGeneric');
                    emailErr.classList.add('show');
                    return;
                }
                showSent();
            });
        };
    }

    function showSent() {
        render(
            '<div class="sent-screen">' +
            '<div class="sent-icon">' +
            '<div class="check-circle">' + I.checkBig + '</div>' +
            '<div class="plane">' + I.plane + '</div>' +
            '</div>' +
            '<h1 class="h-display h-hero">' + esc(T('sent.title')) + '</h1>' +
            '<p class="sub-p sub-center" style="margin-top:.8rem">' + esc(T('sent.sub')) + '</p>' +
            '<div class="ai-dash"></div>' +
            '<button class="btn-p upper" id="doneBtn">' + esc(T('sent.done')) + '</button>' +
            '<div class="btn-caption">' + esc(T('sent.caption')) + '</div>' +
            '</div>'
        );
        document.getElementById('doneBtn').onclick = function () { showResult(true); };
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
        S.statements = s.statements || {};

        switch (s.status) {
            case 'result_ready':
            case 'email_captured':
                if (s.result) {
                    S.result = s.result;
                    showResult(!!s.email_sent);
                    return;
                }
                showIntro(); return;
            case 'comparing': {
                var keys = s.top5 || [];
                S.top = keys.map(byKey).filter(Boolean);
                S.comparisons = s.comparisons || [];
                // rebuild per-value quotes from confirmed answers
                S.quotes = {};
                (s.answers || []).forEach(function (a) {
                    if (!a.confirmed_value_key) return;
                    (S.quotes[a.confirmed_value_key] = S.quotes[a.confirmed_value_key] || []).push(a.answer_text);
                });
                if (nextUnanswered()) { showDuel(); } else { showCompareIntro(); }
                return;
            }
            case 'ai_suggested':
                S.reviewAnswers = (s.answers || []).map(function (a) {
                    return {
                        id: a.id, question_key: a.question_key, answer_text: a.answer_text,
                        suggested_value_key: a.suggested_value_key,
                        confirmed_value_key: a.confirmed_value_key,
                        confidence: a.confidence, source: 'ai',
                    };
                });
                showReview(); return;
            case 'answering': {
                // land on the first question that has no saved answers yet
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

    api('getTestBootstrap', null, 'GET').then(function (d) {
        if (!d.success) {
            app.innerHTML = '<div class="test-loading">' + esc(T('common.errorGeneric')) + '</div>';
            return;
        }
        S.texts = d.texts;
        S.questions = d.questions;
        S.catalog = d.catalog;
        S.bookingUrl = d.booking_url;
        S.session = d.session;
        resume();
    });
})();
