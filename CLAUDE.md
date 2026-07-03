# Vertybių testas — Claude Instructions

## Project overview

Lithuanian-first values test web app for Tomas Petrikaitis. Flow: waiting-list
landing (pre-launch) → intro → blocking privacy/AI + cookie consents → 4 open
questions (multi-answer) → AI maps each answer to one of 191 canonical values →
user confirms/changes → top-5 by frequency → 10 pairwise duels → top-2 (with
tie-breaker) → result with "Ką tai reiškia" + "Galima vidinė įtampa" from the
values catalog → optional email capture → booking CTA (booktomas.com).

Raw PHP 8.3+ + MySQL, vendor-free, mirrors the `smsbite`/`peoplecounter`
conventions. Hosting-agnostic (target: Tomas's own shared hosting).

## Source-of-truth decisions (email thread Tomas ↔ Konstantin)

- LT only for MVP; all copy lives in `ui_texts` (admin-editable).
- Top-5 cutoff ties: AI confidence desc, then first occurrence.
- <5 distinct values → user goes back to questions (no manual picker forcing).
- Top-2 boundary tie: exactly 2 tied → "Lygiosios" tie-breaker duel; 3+ tied →
  result shows ALL tied values (3-4 possible).
- No custom user-entered values. No AI-generated result text.
- Figma is UX source of truth: https://www.figma.com/design/yNXtcktbouPXDsnIUwdS2B/Vertybiu-testas
  (visual verification still pending — exact fonts/layouts unconfirmed).

## Conventions (do not deviate)

- All API requests via `api.php?action=XXX`. Auth tiers: admin key (`?key=`),
  admin session (CSRF), anonymous test session (`vt_session` uuid cookie).
- Migrations: `migrations/YYYY_MM_DD_NNNNNN_description.php`, class with
  `up($db)`/`down($db)`; run via `api.php?action=runMigrations&key=…`.
- Use the `Database` wrapper; no raw PDO. Use `t()`/`te()` for UI texts.
- Admin frontend: `apiCall()` from `js/utils.js` (CSRF auto-retry).
- Clean URLs: pages served extensionless (`/dashboard`, `/privatumas`); internal
  links must NOT use `.php`. `api.php` is the one deliberate exception.
- Ranking logic only in `helpers/ranking.php` (pure functions);
  tests: `php scripts/test_ranking.php`.
- AI calls only via `helpers/openai.php`; mock mode (`ai_mock_mode` setting)
  must keep working — it's the keyless dev path.

## Local dev

```bash
brew services start mysql   # db: vertybes / user: vertybes / pass in .env
php -S 127.0.0.1:8080 router.php   # router.php = clean URLs
curl "http://127.0.0.1:8080/api.php?action=runMigrations&key=dev_admin_key_local"
php scripts/test_ranking.php
```

Admin: `admin@vertybes.local` / `pakeisk_mane_123` (local only).
`waitlist_mode` setting: 1 = landing page, 0 = test app.

## DO NOT

- Commit `.env` or `logs/`.
- Skip CSRF on admin mutations.
- Add Composer/Node dependencies.
- Expose the OpenAI key to the browser (settings API masks it).
