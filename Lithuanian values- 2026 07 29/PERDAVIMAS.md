# Vertybės LT (values test) — handoff for development

Date: 2026-07-21
Prototype: `Vertybiu testas v2 (brand).dc.html` — all 11 screens side by side, interactive. (Earlier 4-screen green version: `Vertybiu testas.dc.html`; design sources: `uploads/vertybes-design-package/`.)

Brand: wordmark **"Vertybės LT"** in every top bar; "vertybių testas" stays a descriptor. Domain **vertybes.lt** (owned). Style: cream `#F7EFDC`, vermilion `#D9432C`, flat (no shadows), 1px `#F2C4AE` card borders, Inter 400/500 only, sentence case, mountain logo. Copy: informal "tu", presupposition-based, no gender-specific endings.

## Screens (11)

Flow: Landing → Consent → Q1 → Q2 → Q3 → Q4 → AI analysis → Result → Kitas žingsnis. The separate review page was REMOVED (it interrupted momentum; most users never edited) — answers stay editable in place until Continue, and the Back button returns to previous questions.

1. **Intro** — hero, checklist, primary CTA, compact 2-layer cookie card (layer 2 = per-category settings via "Nustatymai" / "Slapukų nustatymai")
2. **Before starting** ("Prieš pradedant") — AI info card (sage), 4 guidance rows, privacy link, checkbox; "Tęsti" disabled until checked
3. **Question** — progress "Klausimas 1 iš 4", up to 6 answers (add / remove from the 3rd), short instruction under the heading ("Svarbiausios užuominos apie tave atsiras po kelių atsakymų." — person-focused discovery framing, pre-frames depth before typing starts), no per-row labels (everyday example placeholders instead, one per row: "Pvz. Kelionės", "Pvz. Bėgimas", "Pvz. Laikas su draugais", "Pvz. Skaitymas", "Pvz. Gaminti valgyti", "Pvz. Žvejyba" — deliberately plain, not poetic; remove × sits top-right of the card), NO add button — rows auto-grow: whenever all visible rows are non-empty and fewer than 6 exist, an empty row appears automatically (3rd row placeholder "Kas dar?", 4th–6th everyday examples; GA4 answer_add fires with via: "auto"). Removing an EMPTY row stops auto-grow for that question (user intent respected); removing a filled row does not. Remove × is a 36px round hit area top-right of the card (production: keep ≥44px). Save delight on leaving a field: when the user moves to the next input (blur) and the answer was edited and is non-empty, a small "✓ Atsakymas išsaugotas" pill flashes inside the card (~0.95 s fade cycle); no Enter key needed, unchanged fields do not re-flash; every new row mounts with a 180 ms slide-in (vtRowIn). Very subtle — alive, not congratulatory, and the per-answer coaching feedback placed BETWEEN the last answer field and the CTA (the stop-or-continue decision point). It is the single progress element — no counter, no dots. Behavior: messages appear ONLY when the answer count changes (fade in ~280 ms, the emoji scales 90%→100% first, optional very light vibration on mobile), stay ~2.6 s, then gently fade to a small grey helper "Kiekvienas atsakymas – dar viena užuomina apie tai, kas tau svarbu." (clue metaphor — accurate about what AI does, matches the discovery theme) Message map by filled count: 0 (static hint, always visible when idle) 💡 "Kuo daugiau pavyzdžių, tuo aiškesnis rezultatas." · 1 🌱 "Puiki pradžia. Su kiekvienu atsakymu AI pažįsta tave geriau." · 2 🤖 "AI jau pastebi pasikartojančias temas…" · 3 🔎 "Ryškėja kryptis. Kai pridėsi dar porą, vaizdas taps tikslus." · 4 ✨ "Tavo rezultatas ką tik tapo gerokai tikslesnis." · 5 🎯 "AI jau mato aiškius tavo dėsningumus." · 6 (persistent, no fade) brand mountain SVG + "Puiku. AI jau gali parodyti, kas tave veda." (echoes the result heading "Štai kas tave veda") (the flat vermilion brand mark, NOT the generic 🏔️ emoji). Tone: quiet "something changed", never "congratulations". NOTE: these emoji are an owner-approved exception to the no-emoji brand rule, limited to this coaching line, autosave note. **Minimum 2 answers per question (hard rule).** Tęsti is disabled at 0 answers; at exactly 1 answer clicking Tęsti opens the gate modal (every attempt, not once): title "Pirmas atsakymas jau įrašytas", body "Kol kas AI mato tik vieną tavo pusę." / "Kai pridėsi dar 2–3 atsakymus, rezultatas taps gerokai tikslesnis." / "Tai užtruks mažiau nei minutę.", single button "Pridėsiu daugiau" (closes, focuses the first empty row). There is NO "continue anyway" — the test requires at least two answers; the modal stays supportive in tone (progress framing, never blame). From 2 answers Tęsti continues freely. Wired analytics (●): `single_answer_popup_shown` (params `question_number`, `answers_count`), `single_answer_add_more_clicked`, `single_answer_continue_clicked` (both with `answers_before` / `answers_after` — in production also compare against the final `question_answered.answers_count` to measure whether the nudge improves depth without hurting completion).
4. **AI analysis** — brand mountain draws itself (stroke-dash, ~3.4s loop, fills red), heading "Analizuojame tavo atsakymus…", 3 sequential steps (✓ Ieškome pasikartojančių temų → ✓ Ryškėja svarbiausios vertybės → pulsing ● Vertiname galimą vidinę įtampą…), late-fading seed line "Tai, kas tau svarbu, jau ryškėja. Įdomiausia dažnai slypi giliau." (progress presupposition + curiosity gap — primes the Kitas žingsnis pitch). With the review page gone this screen IS the transition: it builds anticipation while the real back-end analysis runs (tie step timing to actual progress), then auto-advances to the result. For the FIRST ~2.8 s the screen also shows "✓ Atsakymai išsaugoti." at the top and a quiet underlined "Redaguoti atsakymus" link under the steps — clicking it cancels the analysis (production: abort/ignore the backend call) and returns to Question 1 with all answers preserved; after the window both fade away and the analysis continues uninterrupted. Most users never click it — it exists for the "oops" moment.
5. **Comparison** — "Svarbiausios vertybės" + sub "Šios temos tavo atsakymuose kartojosi dažniausiai." (factual, not generated-sounding); up to 5 value cards in a SINGLE column (full-width, premium rhythm); the FIRST card is ~8% larger (bigger padding + name at 1.13rem vs 1rem) purely as a visual entry point, not a ranking. REASONING REVEAL animation: first a centered "🤖 Analizuojame..." status fades in and out (~1 s) under the subtitle, THEN each card rises in (starting ~1.05 s, stagger ~0.38 s), then ITS chips pop in one by one (+0.18 s after the card, then +0.1 s each, slight scale) — the value name lands first, its evidence follows, like AI showing its work. All five cards + chips complete ≈ 3.2 s, CTA section at ≈ 3.45 s (5 values). This trades some CTA delay for the reveal moment — watch comparison_start → first pair_choice in GA4; if drop-off appears, shorten the stagger, not the chips). Each card is deliberately minimal — NO border — a very soft Apple-like shadow (0 2px 8px rgba(64,32,26,.05) + 0 14px 30px -16px rgba(64,32,26,.14)); the single accent is a 9px colored DOT before each value name, one hue per value drawn from the existing brand ramp so every card has its own identity without leaving the palette: Laisvė #D9432C · Artumas #E8845F · Augimas #A0553D · Tiesa #6E2312 · Ramybė #C4A98E (the earlier uniform red top bar was removed — one accent only). Shadow is a deliberate owner-approved exception to the flat no-shadow brand rule, comparison cards only, value name + 2–3 EVIDENCE CHIPS — small cream tags (bg #F7EFDC on the white card, 7px radius, .78rem) with the mentions in base nominative form, distilled from the user's own answers (e.g. Laisvė → [kelionės] [pasirinkimas] [savo laikas]). No labels, no explanations — the chips read as collected evidence. No frequency badges ("+N" removed — users don't know what they mean); full quotes live only on the duel cards. CTA right below the grid; only AFTER the last card the CTA section fades in, in normal flow (floating/sticky CTA was considered and REJECTED for MVP: the compact screen fits an 852px viewport without scrolling, so sticky adds a permanent gradient layer for nothing — revisit only if small-device analytics show the CTA below the fold, e.g. 667px viewports), separated from the cards by a hairline divider: "Beliko suprasti, kurios iš jų tau svarbiausios." (points at the duel, not a generic step) → CTA "Palyginti vertybes" → caption "~ užtruks apie minutę" (owner-approved time estimate); escape link "Kažkas ne taip? Pradėk iš naujo" (restarts test)
6. **Duel** — heading IS the CTA ("Jei šiandien galėtum pasirinkti tik vieną…" — hypothetical frame lowers the stakes of each pick), counter "N / 10", two cards with clear space between them — no "arba" divider (removed: it read like a form; the layout already says or) — value name dominant, under it the value's AI mentions (same `mentions` field as the comparison cards — consistent evidence across screens) as a muted .82rem line with • separators (e.g. Laisvė → kelionės • pasirinkimas • savo laikas) instead of verbatim quotes. Tap = choose, PHYSICAL and fast (Tinder-like rhythm): no selected state, no red confirmation — pressing the card grows it ~2% with a deeper shadow (:active), a tiny ✓ pops in the card corner (0.2 s, momentum feedback), and ~230 ms after the tap the next pair slides in from the right (.25 s). No next button. Caption "Rinkis pirmu impulsu."
7. **Tie-break** ("Lygiosios") — only when technically required (see tie logic). "Paskutinis žodis tavo" / "Šios dvi surinko po lygiai. Vienas pasirinkimas viską išsprendžia." Two name-only cards, top bar shows selection.
8. **Result — partial reveal + email gate** (CRO restructure, owner decision). The exchange is honest: the user gets a REAL answer for free, the depth is exchanged for the email. Chip "✓ Rezultatas paruoštas" (never "baigtas" before the conversion point — the loop must stay open), heading "Štai kas tave veda", full-red hero card (kicker "Tavo pagrindinė vertybė", LAISVĖ, mountain watermark, pop-in) → ONE revealed line — STATIC, not AI-generated: "Šią vertybę tavo atsakymai paminėjo dažniausiai." (same wording for every user; the personalised interpretation stays behind the email) → gate card "Kai įrašysi el. paštą, atrakinsi" (time presupposition: the question stops being IF and becomes WHEN) (NO locked/blurred cards — tried and rejected by the owner: fake locks read as a paywall and cost trust, which matters more than a few points of conversion for a coaching brand; the card states plainly what arrives instead) + reason "Šį rezultatą verta turėti po ranka. Po kelių dienų daugelis pastebi tai, ko pirmą kartą nematė." + promise list straight under the "Atrakinsime" title — ✓ Antrą tavo vertybę · ✓ Ką šis derinys reiškia kasdien · ✓ Kur šis derinys kelia įtampą (no intro line; each item is phrased as something already true in the reader's life, not as a product feature) + email input + solid-red "Gauti rezultatą" + on submit an inline "✓ Atrakinta. Rezultatas išsiųstas." confirmation + the two unbundled consents + "Privatumo politika" → small text link "Praleisti" under the CTA (escape hatch — deliberately a quiet link, not a competing button).
8a. **Atrakinta** (submit path, prototype label "9a") — "✓ Atrakinta" pill (short — the page itself says the result was sent), hero value card, then the three previously locked cards reveal in sequence (~0.15/0.35/0.55 s fades): "Kita stipri vertybė ARTUMAS" · "Ką tai reiškia kasdien" · "Kur ši įtampa jau pasirodo" (copy uses presupposition framing: "kasdien" and "jau pasirodo" assume the pattern is already live in the reader's life; the tension text ends with "Kai pastebėsi, kurioje situacijoje tai vyksta, pamatysi ir sprendimą.") → primary "Kas dar lemia sprendimus? →" (the CTA asks the question the next page answers). Production: this IS screen 8 after a successful submit (same page, no navigation).
8b. **Praleista** (skip path, prototype label "9b") — hero value card + the one free sentence, no locked cards at all, just one last low-pressure offer ("the door is still open") — this form carries the SAME two unbundled consents as the gate (required result consent gating submit + optional marketing opt-in) and the "Privatumo politika" link; consent is required wherever an email is collected in a sage card: "Jei persigalvosi, rezultatas vis dar laukia. Įrašyk el. paštą ir atrakinsi visą." (no guilt framing — never imply they chose wrong) + inline input and "Atrakinti" → ghost "Toliau". If they never submit, they never see the locked content.
9. **Kitas žingsnis — the SHARED continuation page** (e.g. vision.lt/kitas-zingsnis). Both post-result paths (8a submitted, 8b skipped) land on this exact page — there is no second Vision page and no separate success page any more. No email field here (the ask already happened on screen 8). Opens as a REWARD, then a curiosity gap: "Puiku. Dabar žinai, kas tave labiausiai veda." + "Bet šis testas neatsako į vieną svarbų klausimą." + the question itself in heading-2 red: "Kodėl kartojasi tie patys sprendimai?" → "Ko šis testas dar neparodo" (3 rows — the old "Kodėl kartojasi tie patys sprendimų modeliai" bullet was dropped because the page now opens with that exact question) → "Kaip VISION metodas padeda" sage card → full-red VISION hero card "Nuo „žinau savo vertybes" iki „suprantu save geriau"." + "VISION metodas padeda paversti įžvalgas sprendimais." + cream CTA "Pamatyti VISION metodą" (→ https://vision.lt) → "Kai norėsi pažvelgti giliau: Kaip vyksta sesija →" (→ https://vision.lt/kaip-vyksta-koucingo-sesija) → "Nori daugiau panašių įžvalgų? Sekti Vision LT Facebook →" (→ https://www.facebook.com/WhatIfMore/) → mountain mark + "See what matters."
    - Hero "Vertybės yra tik pradžia." + one line "Šį rezultatą verta perskaityti dar kartą." (sets up the email, not the pitch)
    - **Progress stepper** (Zeigarnik — the loop is visibly UNFINISHED), hairline connector behind four markers: ✓ Tavo vertybės · ✓ Galima vidinė įtampa (solid red circle + cream ✓, muted text) · ● Pilnas rezultatas el. paštu (CURRENT: red ring + red centre dot, dark medium text, right-aligned "dabar" pill) · 🔒 Gilesnės įžvalgos — the LOCKED step is the biggest line in the stepper: padlock icon in a soft-red circle, 1.02rem dark-red label + sub-line "Po rezultato išsiuntimo" (no emoji in the UI — a drawn padlock). Production ticks step 3 and unlocks step 4 after submit.
    - **Email section** — "Gauk rezultatą į el. paštą" + ONE reason: "Šį rezultatą verta turėti po ranka. Po kelių dienų daugelis pastebi tai, ko pirmą kartą nematė."; label "El. paštas", full-width input, full-width "Gauti rezultatą" button (short by design — the heading already says what happens); TWO unbundled consents (required result consent gates submit; optional "Noriu gauti ir Vision LT įžvalgas el. paštu. (pasirinktinai)"); small "Privatumo politika" link
    - Footer: mountain mark + "See what matters."
    - Production: a successful submit transforms this page into screen 10 (no separate navigation) — success first, then the VISION story.
    - Hero "Vertybės yra tik pradžia." + one line "Šį rezultatą verta perskaityti dar kartą." — the sub-line now sets up the EMAIL (a reason to come back to the result), not the Vision pitch
    - **Progress stepper** (Zeigarnik — the loop is visibly UNFINISHED so the brain wants to close it), read as a vertical stepper: a hairline connector line runs behind the four markers. ✓ Tavo vertybės · ✓ Galima vidinė įtampa (done: solid red circle + cream ✓, muted text) · ● Pilnas rezultatas el. paštu (CURRENT: red ring with red centre dot, dark medium-weight text, right-aligned "dabar" pill) · ○ Gilesnės įžvalgos (pending: faint ring + faint text). Static in the prototype; production ticks step 3 after submit.
    - **Email section** (see the full spec two bullets below) — FIRST action on the page, then a hairline divider
    - "Ko šis testas dar neparodo" — 4 "?" rows
    - "Kaip VISION metodas padeda" — sage card: "VISION metodas padeda ne tik suprasti save, bet ir veikti pagal tai, ką supratai."
    - **Page HERO** — full-red block: "Nuo „žinau savo vertybes" iki „suprantu save geriau"." + sub "VISION metodas padeda paversti įžvalgas sprendimais." + cream CTA "Pamatyti VISION metodą (→ https://vision.lt)"
    - Email section (positioned above, right under the hero): "Gauk rezultatą į el. paštą" + ONE reason line under it: "Prie šio rezultato verta sugrįžti po savaitės. Dažniausiai tada pastebimos svarbiausios įžvalgos.", one row = input ("Tavo el. paštas") + solid-red "Gauk šį rezultatą į el. paštą", small consent "Sutinku gauti rezultatą ir Vision LT įžvalgas." + small "Privatumo politika" link
    - One-line links: "Kai norėsi pažvelgti giliau: Kaip vyksta sesija →" (links to https://vision.lt/kaip-vyksta-koucingo-sesija, new tab) and "Nori daugiau panašių įžvalgų? Sekti Vision LT Facebook →" (links to https://www.facebook.com/WhatIfMore/, opens in a new tab) (no "coaching"/"book now" wording)
    - Footer "Prie šio rezultato verta sugrįžti." + mountain mark + "See what matters."
10. *(removed)* — the standalone "Sent" page is gone: the submit confirmation now lives on 8a ("✓ Rezultatas išsiųstas") and the VISION story lives on the shared page 9. Do not reintroduce a post-Vision success page.
11. **Privacy & AI** — the FULL owner-approved privacy policy (updated 2026-07-22), 10 numbered sections: 1 Kas esame (controller Boeder Equipments Limited, 4th Floor East, 35–37 Ludgate Hill, London EC4M 7JN, UK; contact tomas@vertybes.lt) · 2 Kokius duomenis renkame · 3 Kam naudojame · 4 AI naudojimas (OpenAI API; data not used for training per default API settings; AI does not assign values by itself) · 5 Kam perduodame (MailerLite, OpenAI Ireland Ltd./OpenAI, technical providers) · 6 Kiek laiko saugome · 7 Tarptautinis perdavimas (SCC) · 8 Tavo teisės (GDPR list + complaint to Valstybinė duomenų apsaugos inspekcija; requests to tomas@vertybes.lt) · 9 Slapukai (GA/Meta Pixel only after consent) · 10 Pakeitimai. The bottom sheet carries the same 10 sections in compact prose. The detailed retention tiers (answers 30 d post-analysis, result while subscribed, anonymized without email) remain the implementation spec for the developer — the policy states the same in plainer terms.

## Flow logic

**Answers editable, AI values not.** Write answers (editable in place until Continue; Back returns to previous questions; everything autosaves) → after the final question's Continue answers are saved automatically, a "✓ Atsakymai išsaugoti." acknowledgment flashes ~0.5–0.8 s, and the AI analysis starts with NO confirmation click → AI maps values silently → system picks strongest candidates → compare. There is no separate review page and no per-value editing; the only escape hatch is the restart link on the comparison screen.

**Comparison: 3–5 values, every unique pair once.** Selection by (1) frequency across answers, (2) AI confidence, (3) strength/specificity of evidence. Pairs = n(n−1)/2: 3 → 3, 4 → 6, 5 → 10. Under 3 qualifying values: don't start the comparison, ask for more answers. Heading and duel counter take the real count (prototype: Tweaks → valueCount 3–5).

**Ties: no standard tie screen.** Resolve silently: (1) total duel wins, (2) original frequency in answers, (3) only if still tied — ONE extra direct comparison (screen 8). Most users never see it.

## Analytics (installed in the prototype's head)

**gtag.js** `G-GGS59F2SHD` with **Consent Mode v2**: `analytics_storage: denied` by default BEFORE `config`; cookie accept fires `gtag('consent','update',{analytics_storage:'granted'})`. Copy the same block to production.

**Meta Pixel** `1012985031473778`: base code with `fbq('consent','revoke')` before `init`; `fbq('consent','grant')` fires with the GA4 consent update. Wired: `PageView` (queued until grant), `Lead` (successful email submit).

**Microsoft Clarity** (project xq3b5kvo6i) is installed in the prototype's <head>: the standard tag plus `clarity('consent', false)` right after load (no cookies before the banner choice); the cookie-accept handler fires `clarity('consent', true)` together with the GA4/Pixel consent updates. Copy the same pattern to production — session recordings/heatmaps only after consent.

**Event naming**: prefix `vertybiu_testas_*` (same property as `laisves_auditas_*`; shared stages stay comparable: view → start → question_answered → quiz_complete → result_view → email_submit). Don't reuse the deprecated `*_q_complete` pattern.

Events in flow order (● = already wired in the prototype via a `track()` helper; the rest need real navigation, wire in production):

1. ● `view` — page loaded
2. `start` — intro CTA
3. `consent_complete` — param `policy_viewed` bool
4. `question_answered` — once per question on submit; params `question_number`, `answers_count`
5. ● `answer_add` — params `question_number`, `answer_index` (on add, not per keystroke)
5a. ● `single_answer_popup_shown` / ● `single_answer_add_more_clicked` — the min-2 gate modal (fires on every Tęsti attempt with 1 filled answer); params `question_number`, `answers_count` (shown) and `answers_before` / `answers_after` (click). `single_answer_continue_clicked` was REMOVED with the "Tęsti vis tiek" option — there is no continue path below 2 answers
8. `analysis_view`
8a. ● `analysis_edit_click` — the 2–3 s "Redaguoti atsakymus" escape on the analysis screen (cancels analysis, returns to Q1, answers preserved)
9. `comparison_start` — params `values_count`, `pairs_total`
10. ● `pair_choice` — params `pair_index`, `chosen_value`, `other_value`
11. ● `tiebreak_choice` — params `chosen_value`, `other_value`
12. `quiz_complete` — last pair resolved, before result renders
13. `result_view` — screen 8 (gate) renders; params `value_1`, `value_2`
13a. ● `gate_skip` — the small "Praleisti" link on screen 8
13b. `unlock_view` — screen 9a (revealed) renders; params `screen: '9a'`, `value_1`, `value_2`. Fire once when the unlocked state mounts, not on every re-render
13c. `skip_view` — screen 9b renders; param `screen: '9b'` (fires together with `gate_skip`, which records the click itself)
13d. `reminder_submit` — the "Atrakinti" button in the 9b reminder card; fire it ALONGSIDE `email_submit` (same submit, this one just marks the second-chance source), param `screen: '9b'`
13e. `next_step_click` — "Kas dar lemia sprendimus? →" on 9a; param `source: '9a'` (from 9b's "Toliau" send `source: '9b'`)
14. `next_step_view` — the shared VISION page renders
15. ● `email_submit` — key event; params `value_1`, `value_2`, `marketing_opt_in` (true/false); MailerLite subscribe alongside (marketing group only on opt-in)
16. ● `submit_error` — param `reason`: invalid_email | no_consent | api_error
17. `vision_method_click`, 18. `session_info_click`, 19. `follow_click` (fires on both follow links — Kitas žingsnis and Sent pages; param `source`: next_step | sent)
20. ● `privacy_open` — param `source`
21. ● `cookie_accept` / `cookie_decline` — settings save passes `via: settings`; accept also fires the consent updates
22. `restart`

**⚠ Checked in GA4 on 2026-07-29 — production is only reporting 5 custom events**: `view`, `question_answered`, `answer_add`, `pair_choice`, `cookie_accept`. Everything else in this list is NOT firing on the live site, including the key event `email_submit` (and `quiz_complete`, `result_view`, `submit_error`, `restart`). Without `email_submit` the funnel cannot be measured at all — wire it first. Reference implementation (already working in the prototype's submit handler):

```js
gtag('event', 'vertybiu_testas_email_submit', {
  value_1: mainValue, value_2: secondValue,
  marketing_opt_in: marketingChecked,
  source: attribution.source, referral_code: attribution.referral_code,
  entry_point: 'gate' // 'reminder' when submitted from 9b
});
```
Fire it only on a SUCCESSFUL submit (valid email + required consent), once per submit, before/parallel to the MailerLite call — not on button click. Mark it as a Key event in GA4 (Admin → Events).

Key funnel: start → question_answered(1) → question_answered(4) → comparison_start → quiz_complete → result_view → email_submit. (review_edit / review_submit events removed with the review step.) result_view → email_submit measures the email placement; vision_method_click / next_step_view is the transition-page conversion.

## Lead attribution & leads database

Partner links: `https://vertybes.lt/?source=coach&referral_code=tomas123`. Working in the prototype:

- Params persisted to `localStorage.vt_attribution` (+`first_seen`), **first touch wins**. Production: mirror to a 90-day first-party cookie.
- Both values ride on every GA4 event (`source`, `referral_code`, `(none)` when absent — register as custom dimensions) and on Meta `Lead`.
- Successful submit saves the lead (prototype: `localStorage.vt_leads` + console.log; production: real DB).

**Client storage is not trusted** — it's user-editable. Production: validate `referral_code` server-side against the coaches table (unknown → flag `unverified`); the server writes attribution into the lead row itself; payouts/reports run off the DB, deduped by unique email; optionally HMAC-signed links later.

Leads table (minimum): `id` uuid PK · `email` text unique · `value_1`, `value_2` text · `source` text nullable · `referral_code` text nullable (indexed) · `consent` bool + `consent_version` · `consent_at` / `created_at` timestamptz · `test_session_id` uuid · `mailerlite_subscriber_id` text.

**Retention policy** (owner decision 2026-07-22: NO auto-delete at MVP): raw answers, result (value_1/value_2) and email are all kept while the service keeps data — the purpose is the product promise "prie šio rezultato verta sugrįžti" plus retake comparison. On (a) an erasure request to tomas@vertybes.lt or (b) unsubscribe cleanup, personal data is ANONYMIZED rather than wiped: delete the lead row (breaking the person link), keep the answers rows — they carry only a pseudonymous session_id, so they become anonymous statistics (stated in policy §6). Sessions without email are pseudonymous from the start.

**Raw answers in SQL** (owner request): /api/analyze also stores every answer in the D1 `answers` table (id, session_id, question_number, answer_text, created_at) and returns `session_id`; the front-end passes it to /api/leads as `test_session_id`, linking lead ↔ answers. Privacy rules that make this legal: (1) covered by policy §2 ("tavo pateiktus atsakymus") and §6 — no policy change needed; (2) pseudonymous — keyed by session_id, joined to an email only via the leads row; (3) NEVER sent to MailerLite; (4) no auto-delete at MVP — removed only on erasure request or unsubscribe cleanup (see retention policy above); (5) coach reports must use values only, never raw answer texts.

MailerLite: pass `lead_source` (the URL/GA4 `source` value — renamed because "source" is reserved in MailerLite), `referral_code`, `value_1`, `value_2` as subscriber custom fields. Coach report = leads grouped by `referral_code` (DB is source of truth; GA4 gives the funnel per code).

## Palette and tokens

All colors flow through `--vt-*` custom properties set in one place. Tweaks: preset (Brand / Žalia produkcija / Individualu) + 10 individual colors.

Brand: bg `#F7EFDC`, accent `#D9432C`, accent-dark `#6E2312`, text `#40201A`, muted `#A0553D`, soft/sage `#F2C4AE`, tan `#E8845F`, lines `#F2C4AE`, on-red `#F7EFDC`. Green preset = production public.css :root values (restores shadows, Playfair, uppercase). Structural tokens change with the preset: heading font/weight/color, shadows, card borders, letter-spacing, buttons.

## Interactions working in the prototype

- Question screen: add/remove answers, auto-grow, focus borders, per-answer coaching, "✓ Atsakymas išsaugotas" blur flash; "✓ Atsakymai išsaugoti." pill flashes on Continue with ≥2 answers (the analysis transition in production)
- AI analysis screen: "✓ Atsakymai išsaugoti." + "Redaguoti atsakymus" visible the first ~2.8 s after load; the link scrolls back to Question 1 (prototype) / cancels analysis and returns to Q1 (production)
- Cookie card: collapsed/expanded, Būtini always on + Statistika checkbox, "Išsaugoti pasirinkimą"
- "Prieš pradedant": Tęsti disabled until checked
- Duel: tap → red flash → auto-advance (450 ms); last pair loops to pair 1 in the prototype (production: navigate to tie-break/result)
- Email form (Kitas žingsnis screen): regex + consent validation, error colors, success note
- Privacy links open a bottom sheet; cookie sheet on intro
- No navigation between frames (all screens side by side)

## Legal decisions

- **Cookies** = site-level → intro bottom sheet (Necessary always on + Statistics optional)
- **Data + AI consent** = test-level → "Prieš pradedant" checkbox; record the consent fact (date + version) before question 1
- Copy for screens 2, 5, 7, 8, 11, 12, cookie texts, and the privacy/AI page was written during prototyping. **Privacy, AI, and cookie texts are a draft — lawyer review before launch.**

## Copy principles (bind production copy)

Constraints agreed with the owner: no "more answers = more accurate" claims, no price anchoring, no confirmshaming, no fake numbers; consent elements stay neutral.

- Cookie layer 1: commitment + loss aversion ("Būtini slapukai saugo tavo atsakymus, kad pradėtas testas nedingtų."), CTA "Leisti visus ir tęsti", quiet "Tik būtini" / "Nustatymai" (both choices one click — compliance)
- Intro: headline "Kas iš tikrųjų lemia tavo sprendimus?", CTA "Atrasti savo vertybes", checklist = friction removers ("~3 minutės" / "Be registracijos" / "Tik 4 klausimai. Atsakyti gali ir vienu žodžiu." / "Rezultatas iš karto")
- Before starting: persuasion-free consent; AI card gives agency ("Kurios iš tikrųjų tavo — nuspręsi tu."); the privacy-link line opens with a Langer "because" reason ("Tavo atsakymai — asmeniški, todėl klausiame.")
- Question: depth pre-frame instruction ("Svarbiausios užuominos apie tave atsiras po kelių atsakymų."), goal-gradient counter (N iš 6), no add button — the next empty row appears by itself as answers fill (auto-grow), progressive coaching between the fields and the add button
- Comparison/Duel/Result: ownership presuppositions, no time promises, no price mentions
- Email (screen 10): sold as future value ("Gauk rezultatą į el. paštą" + ONE reason line under it: "Prie šio rezultato verta sugrįžti po savaitės. Dažniausiai tada pastebimos svarbiausios įžvalgos."), never as "a summary"

## Notes for the developer

- public.css / test.css classes map to prototype elements: `btn-p`, `field-card`, `duel-card-p`, `tb-card`, `review-card`, `value-cell`, `sheet`/`cookie-card`, `result-value-card`, `meaning-card`. For the brand variant update :root variables, remove shadows/uppercase.
- Persist the cookie choice and don't reshow the sheet; enable analytics only after consent.
- Email sending is fully MailerLite's job (automation on subscribe); the back-end never sends mail itself.
- Static in the prototype: question progress (25%), duel counter.

## Test content: the 4 questions (final LT copy)

1. „Ką labiausiai mėgsti veikti laisvalaikiu?" — energy/joy signals; baseline weight
2. „Kas tave labiausiai suerzina ar nuvilia?" — violated values; carries the evidence-rule weight multiplier (anger points at what matters)
3. „Kuo savo gyvenime labiausiai didžiuojiesi?" — expressed values (peak moments); the main source of specific, personal evidence
4. „Be ko tavo gyvenimas prarastų prasmę?" — essentials; the cross-check question: a value repeating here AND in 1–3 is exactly the cross-question frequency the selection rules reward

Same screen layout, guidance, and answer mechanics for all four; only the heading changes ("Klausimas N iš 4").

## Values dictionary (AI maps ONLY to this list)

Laisvė · Savarankiškumas · Drąsa · Nuotykiai · Smalsumas · Augimas · Meistrystė · Kūryba · Pasiekimai · Pripažinimas · Įtaka · Tiesa · Autentiškumas · Teisingumas · Atsakomybė · Pagarba · Saugumas · Ramybė · Sveikata · Harmonija · Disciplina · Artumas · Šeima · Bendruomenė · Empatija · Dosnumas · Prasmė · Dvasingumas · Tradicijos · Gamta · Grožis · Žaismingumas (32 — FINAL owner-approved list, 2026-07-22; matches VALUES_DICT in cloudflare/worker.js — keep the two in sync)

Rules: the model may never invent a value outside the list; near-synonyms collapse to the canonical name (e.g. "nepriklausomybė" → Savarankiškumas; "kelionės" → Laisvė or Nuotykiai by context). Owner can extend the list; keep it ≤ ~35 so results stay comparable (synonym folding is the guard against fragmentation — e.g. "sąžiningumas" → Tiesa, "finansinis saugumas" → Saugumas, "nepriklausomybė" → Savarankiškumas, "humoras/juokas" → Žaismingumas, "meilė/draugystė/lojalumas" → Artumas ar Šeima pagal kontekstą).

## AI contract

Input: `{ session_id, answers: [{ question_number: 1–4, text }] }` — no name/email (PII stripped before the call).
Output (strict JSON): `{ values: [{ name: <from dictionary>, confidence: 0–1, evidence: [{ quote, question_number }], mentions: [string, max 3] }] }` — quotes verbatim from the user's answers; `mentions` are the SHORT bullets shown on the comparison cards ("Nes dažnai minėjai:"): 1–3 items, each distilled ONLY from that value's evidence quotes (paraphrase into base nominative form, lowercase, ≤3 words / ~24 chars, e.g. „pats planuoju laiką" → „savo laikas"), never invented. Limiting is enforced in three layers: JSON schema `maxItems: 3` on the AI call, server-side `slice(0, 3)` + per-item trim as a guard, and single-line CSS ellipsis in the card as the last resort; max 5 values after the selection rules (frequency → confidence → evidence strength), min 3 or the flow asks for more answers.

Evidence rules for selecting the 3–5 values (in priority order):

1. **Cross-question frequency weighs most** — a value appearing in 2–3 different questions (e.g. "kelionės" in leisure AND "kontrolė erzina" in irritations) beats one backed by 3 quotes from a single question.
2. **Specificity beats declaration** — "pats planuoju savo laiką" is strong evidence; "laisvė man svarbu" is weak (people declare what they'd like, not what drives them).
3. **Question 2 (kas suerzina) gets a weight multiplier** — a violated value's emotional signal is more reliable than a hobby mention.
4. **Minimum threshold** — a value backed by a single generic quote does not qualify; better 4 strong candidates than 5 with one weak (this is why the prototype's dropped 6th value was "Kompetencija").
Constraints: low temperature; retry once on malformed JSON; on second failure show a friendly retry state, never a blank screen. Provider: OpenAI API (API data is not used for training per OpenAI terms — also stated in the privacy policy).

Interpretations ("Ką tai reiškia kasdien", tension card, combined line on Kitas žingsnis): generated at result time from value_1 + value_2 with a fixed prompt (informal "tu", 2 sentences each, no gender endings, no promises); cache per value pair — the same pair always gets the same text; owner reviews the ~10 most frequent pairs after launch.

## Result email (MailerLite automation — no custom mailer)

The email is sent by a MailerLite automation triggered on subscribe; the back-end only creates the subscriber with custom fields. Subject: „Tavo vertybės: {value_1} ir {value_2}". Blocks: brand header (mountain + Vertybės LT) → the two values (№1 dominant, same hierarchy as the result screen) → interpretation → one reflection question → CTA button to the Kitas žingsnis page → unsubscribe + Boeder Equipments footer. `value_1`, `value_2`, `source`, `referral_code` come from subscriber custom fields.

## MailerLite connection — setup instructions

**Owner side (no code, ~30 min):**
1. Verify the sender domain (Settings → Domains → add the SPF + DKIM DNS records for vision.lt/vertybes.lt) — without this the result emails land in Spam.
2. Create groups: `vertybiu-testas` (every lead joins) and `vertybiu-testas-marketing` (ONLY those who ticked the optional insights checkbox).
3. Create custom fields (Subscribers → Fields): `value_1`, `value_2`, `lead_source`, `referral_code`, `consent_version` (text; "source" is a reserved name in MailerLite — use `lead_source`) — used for personalization and coach reports.
4. Create the result automation: Automations → trigger "joins group vertybiu-testas" → one email — subject "Tavo vertybės: {$value_1} ir {$value_2}", body per the Result email spec above. A second automation on the marketing group handles the weekly insights.
5. IMPORTANT: turn double opt-in OFF for the test group (the result must arrive instantly; consent is collected by the required checkbox and logged with version + timestamp). Confirm this choice with the lawyer.
6. Generate an API token (Integrations → MailerLite API → new token). It is SECRET — server-side only, never in the browser bundle.

**Developer side:**
- `POST https://connect.mailerlite.com/api/subscribers` with header `Authorization: Bearer <TOKEN>`, JSON body: `{ "email": "...", "fields": { "value_1": "...", "value_2": "...", "lead_source": "...", "referral_code": "...", "consent_version": "v1" }, "groups": ["<test-group-id>", ...(marketing opt-in ? ["<marketing-group-id>"] : [])] }` — called from `POST /api/leads`, AFTER the lead row is saved to the DB.
- Save the returned `data.id` into `leads.mailerlite_subscriber_id`.
- Error handling: 422 = invalid email → return the form error; 5xx/timeout → still return success to the user (the lead is safe in the DB), flag the row `ml_pending` and retry with a cron.
- Unsubscribe: MailerLite handles the link; a daily job deletes/anonymizes leads unsubscribed >30 days AND their answers rows (join via test_session_id).
- Test before launch: submit a test lead with and without the marketing checkbox → check both groups, field values, automation delivery, and the unsubscribe flow.

## Cloudflare Worker (ready code in /cloudflare)

Working API implementation lives in the project: `cloudflare/worker.js` (endpoints /api/leads + /api/analyze per the specs below), `cloudflare/schema.sql` (D1: leads + coaches tables), `cloudflare/wrangler.toml` (routes, D1 binding, vars). Deploy steps (CLI — alternatively use the DASHBOARD path below, no wrangler needed):

1. `npm i -g wrangler && wrangler login`
2. `wrangler d1 create vertybes-db` → paste the returned database_id into wrangler.toml
3. `wrangler d1 execute vertybes-db --file=cloudflare/schema.sql` (add coaches: `INSERT INTO coaches (code,name) VALUES ('tomas123','Tomas');`)
4. `wrangler secret put MAILERLITE_TOKEN` and `wrangler secret put OPENAI_API_KEY`
5. Fill ML_GROUP_TEST / ML_GROUP_MARKETING group IDs in wrangler.toml (MailerLite → Groups → ID from the URL)
6. `wrangler deploy` (route vertybes.lt/api/* is preconfigured; the zone must be on Cloudflare)

**Dashboard path (no CLI):** Workers & Pages → Create Worker `vertybes-api` → Edit code → paste worker.js → Deploy. D1 → Create `vertybes-db` → Console → run schema.sql + coach INSERTs. Worker Settings → Bindings → D1 binding `DB` → vertybes-db. Settings → Variables & Secrets → MAILERLITE_TOKEN + OPENAI_API_KEY as **Secrets** (never in code), ALLOWED_ORIGIN / ML_GROUP_TEST / ML_GROUP_MARKETING as plain vars. Settings → Domains & Routes → `vertybes.lt/api/*`. wrangler.toml is CLI-only and unused on this path.

Built-in behaviors: DB-first lead saving (MailerLite failure leaves ml_pending=1 for a cron retry), referral_code verified against the coaches table (referral_verified flag), marketing group only on opt-in, email upsert by unique key, CORS locked to ALLOWED_ORIGIN, OpenAI called without PII (answers only), strict-JSON analysis with one retry and dictionary/mentions enforcement (3–5 values, mentions ≤3 × ≤24 chars). Not included (add before scale): rate limiting (Cloudflare WAF rule or KV counter) and the 30-day retention cron.

## Back-end endpoints (minimum)

- `POST /api/sessions` → `{ session_id }` (created at consent_complete; stores consent fact + version + timestamp)
- `PATCH /api/sessions/:id/answers` — autosave on every input (debounced) and on add/remove; on return (same device) the session is restored automatically and the user lands on their last question — no visible "save for later" button. This backs the "Atsakymai išsaugomi automatiškai" note
- `POST /api/sessions/:id/analyze` → AI contract above
- `POST /api/sessions/:id/choices` — duel/tie picks (server computes the winner + tie logic)
- `POST /api/leads` — email + consent + attribution; validates referral_code, saves to DB, then calls the MailerLite subscribe API (custom fields) — MailerLite's automation takes over from there; no custom email sending anywhere
Rate-limit all endpoints; session_id = uuid v4.

## Desktop behavior

MVP: the mobile column (max-width ~480px) centered on the cream background, top bar full-width. No separate desktop layout.

## Acceptance criteria (QA)

- No analytics/pixel cookies before cookie accept (check DevTools); each event fires exactly once per action
- "Tęsti" gates work: consent checkbox; question CTA disabled at 0 answers, gate modal at exactly 1; Q4 Continue → auto-save acknowledgment → analysis with no extra click
- Review page no longer exists anywhere in the flow; pressing Continue on Question 4 immediately starts the AI analysis
- Back button returns to previous questions with answers intact (before analysis finishes, "Redaguoti atsakymus" on the analysis screen does the same within its ~2.8 s window and cancels the analysis)
- AI analysis screen acts as the bridge: step animation runs while the backend call is in flight, auto-advances to the result when done
- 3/4/5-value sessions produce 3/6/10 duels; tie screen appears only on a true tie after the two silent rules
- Lead lands in DB + MailerLite with correct value_1/value_2, source, referral_code (test with and without URL params)
- Result email renders in Gmail/Outlook mobile
- Refresh mid-test restores answers (session autosave)
- Lighthouse mobile: Performance ≥ 85, Accessibility ≥ 95

### Dynamic microcopy on the question screen (client-side JS, no PHP)

- There is NO add button. Auto-grow rule: whenever every visible row is non-empty and rows < 6, append one empty row (3rd gets placeholder "Kas dar?"); set a growStopped flag when the user removes an EMPTY row and stop auto-growing for that question; fire answer_add with via: "auto" on each auto-append.
- Per-answer coaching (screen 3): fires only on count CHANGE; fade in ~280 ms (emoji first, scale .9→1), hold ~2.6 s, fade to grey helper "Kiekvienas atsakymas – dar viena užuomina apie tai, kas tau svarbu."; count 6 persists with the brand mountain SVG. Full message map is in the screen-3 spec above. The Review screen keeps a plain positive counter instead: "N atsakymai ✓" (1 → "1 atsakymas ✓"; 0 → the real-reason hint; 6 → "Įrašei visus 6.").
- Re-run on input, add, remove.

```js
function refreshAnswersUI() {
  const inputs = [...document.querySelectorAll('.answers-stack input')];
  const rows = inputs.length;
  const filled = inputs.filter(i => i.value.trim()).length;
  if (!growStopped && rows < 6 && filled === rows) appendEmptyRow(); // 'Kas dar?' on the 3rd
  // Coaching messages: keep the LAST SHOWN count in a var; when 'filled' changes,
  // render COACH[filled] (emoji + text), animate in, and after ~2.6 s swap to the
  // grey helper line. COACH map — see the screen-3 spec. filled === 6 persists.
}
```

(Selectors indicative; match production markup.)
