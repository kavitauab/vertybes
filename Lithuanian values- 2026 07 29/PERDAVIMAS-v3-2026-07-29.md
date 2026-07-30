# Vertybės LT — v3 (2026-07-29)

Scope: **only the end of the funnel changed** (from the result screen onwards). Questions, AI analysis, value comparison and duels are untouched.
Full specification stays in `PERDAVIMAS.md`; this file lists only what a developer must change.

---

## 1. New post-result flow

```
Duels
  ↓
8  RESULT  (email gate)
  ├── "Gauti rezultatą"  → 9a  (unlocked)
  └── "Praleisti"        → 9b  (skipped)
                              ├── "Atrakinti" (email) → 9a
                              └── "Toliau"            → 10
9a → "Kas dar lemia sprendimus? →" → 10
10  KITAS ŽINGSNIS (shared VISION page, endpoint for both paths)
```

**Removed:** the standalone "Išsiųsta" success page, the email block that used to live on the "Kitas žingsnis" page, and every duplicate VISION section. There is now exactly one VISION page (10) and one success state (9a).

---

## 2. Screen 8 — result becomes an email gate

Shown for free:
- primary value (LAISVĖ in the prototype)
- one **static** line, identical for every user: *"Šią vertybę tavo atsakymai paminėjo dažniausiai."* (no AI generation here)

Not shown on this screen: second value, meaning, inner tension.

Instead, a gate card:
- title *"Kai įrašysi el. paštą, atrakinsi"*
- ✓ Antrą tavo vertybę · ✓ Ką šis derinys reiškia kasdien · ✓ Kur šis derinys kelia įtampą
- email field + primary CTA **"Gauti rezultatą"**
- small text link **"Praleisti"** (secondary, not a button)
- chip reads *"Rezultatas paruoštas"* — never "baigtas" before the email step

No blurred or locked placeholder cards anywhere (deliberate product decision: fake locks read as a paywall and cost trust).

---

## 3. Screens 9a and 9b

**9a — email submitted.** Badge "✓ Atrakinta", then primary value → second value → *"Ką tai reiškia kasdien"* → *"Kur ši įtampa jau pasirodo"*, revealed in sequence. Primary CTA **"Kas dar lemia sprendimus? →"** → screen 10.

**9b — email skipped.** Primary value + the same static line, no locked cards. One low-pressure reminder card: *"Jei persigalvosi, rezultatas vis dar laukia. Įrašyk el. paštą ir atrakinsi visą."* with an email field and **"Atrakinti"** (→ 9a). Secondary **"Toliau"** → screen 10.

---

## 4. Two email capture points — same rules in both

Email is now collected in **two** places: the gate on screen 8 and the reminder card on screen 9b.

Everything that applied to the previous single form applies to **both**, unchanged:
- **Required consent** (transactional): *"Sutinku, kad mano el. pašto adresas būtų naudojamas testo rezultatui atsiųsti."* — blocks submit until ticked, turns red on error.
- **Optional consent** (marketing): *"Noriu gauti ir Vision LT įžvalgas el. paštu. (pasirinktinai)"* — never pre-ticked, never bundled with the required one.
- `consent_version` is recorded with the lead: **v1** when only the required consent is ticked, **v2** when both are ticked (see the bug below).
- "Privatumo politika" link under the checkboxes.
- MailerLite: subscribe always (transactional result); add to the marketing group/automation **only** when the optional box is ticked.

---

## 4b. BUG — MailerLite `consent_version` always sent as v1

```
Fix MailerLite consent_version logic.

Checkbox 1: "Sutinku, kad mano el. pašto adresas būtų naudojamas testo rezultatui atsiųsti."
Checkbox 2: "Noriu gauti ir Vision LT įžvalgas el. paštu. (pasirinktinai)"

If only Checkbox 1 is checked          -> send consent_version = v1
If Checkbox 1 AND Checkbox 2 are checked -> send consent_version = v2

Current bug: even when both checkboxes are checked, the backend sends
consent_version = v1. It must send v2.

Applies to BOTH email capture points (screen 8 gate and screen 9b reminder).
The value must be derived from the actual checkbox state at submit time,
not from a default constant. Marketing group subscription in MailerLite
must follow the same condition as v2.
```

## 5. Data sent to MailerLite is unchanged

Screen 8 no longer *displays* the second value, the meaning text and the inner tension — but they are still computed and **must still be sent to MailerLite exactly as before**: `value_1`, `value_2`, plus the interpretation and tension texts used by the result email, together with `lead_source`, `referral_code` and `consent_version`. Hiding them is a UI decision only; nothing changes in the data layer or in the email template.

The same payload is sent whether the email came from the gate (screen 8) or from the reminder (screen 9b).

---

## 6. GA4

**Blocking issue:** checked in GA4 on 2026-07-29 — production only reports `view`, `question_answered`, `answer_add`, `pair_choice`, `cookie_accept`. The key event **`vertybiu_testas_email_submit` is not firing at all**, so the funnel cannot be measured. Wire it first and mark it as a Key event.

```js
gtag('event', 'vertybiu_testas_email_submit', {
  value_1: mainValue,
  value_2: secondValue,
  marketing_opt_in: marketingChecked,
  entry_point: 'gate',            // 'reminder' when submitted on 9b
  source: attribution.source,
  referral_code: attribution.referral_code
});
```
Fire only on a successful submit (valid email + required consent), once per submit.

New events for the new screens:

| Event | When | Params |
|---|---|---|
| `vertybiu_testas_result_view` | screen 8 renders | `value_1`, `value_2` |
| `vertybiu_testas_gate_skip` | "Praleisti" clicked on 8 | – |
| `vertybiu_testas_unlock_view` | 9a renders | `screen: '9a'`, `value_1`, `value_2` |
| `vertybiu_testas_skip_view` | 9b renders | `screen: '9b'` |
| `vertybiu_testas_reminder_submit` | "Atrakinti" on 9b — fire **alongside** `email_submit` | `screen: '9b'` |
| `vertybiu_testas_next_step_click` | CTA into screen 10 | `source: '9a'` or `'9b'` |

Funnel to build in GA4: `view → question_answered → quiz_complete → result_view → email_submit → next_step_click`.

---

## 7. Screen 10 — the shared VISION page

Opens as a reward, then a curiosity gap:

> Puiku. Dabar žinai, kas tave labiausiai veda.
> Bet šis testas neatsako į vieną svarbų klausimą.
> **Kodėl kartojasi tie patys sprendimai?**

Then: "Ko šis testas dar neparodo" (3 rows) → "Kaip VISION metodas padeda" → VISION CTA (https://vision.lt) → "Kaip vyksta sesija →" (https://vision.lt/kaip-vyksta-koucingo-sesija) → Facebook (https://www.facebook.com/WhatIfMore/) → "See what matters."

No email field on this page.

---

## Acceptance checklist

- [ ] 8 → 9a on submit, 8 → 9b on "Praleisti"; 9b → 9a on submit, 9b → 10 on "Toliau"; 9a → 10
- [ ] Both email forms enforce the required consent and keep the marketing opt-in separate
- [ ] MailerLite still receives `value_2`, interpretation and tension text even though screen 8 hides them
- [ ] `email_submit` fires in production with `entry_point` and is marked as a Key event
- [ ] `consent_version` = v2 when both checkboxes are ticked (currently always v1), on both forms
- [ ] No success page after VISION, no second VISION page, no locked placeholder cards
