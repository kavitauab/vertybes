# Vertybių testas

Lithuanian-first values test web app: 4 open questions → AI maps answers to a
canonical catalog of 191 values → user confirms 5 → 10 pairwise comparisons →
top-2 values with pre-written interpretation → optional email capture.
Includes a built-in admin panel where all texts, questions, values, AI prompt
and settings are editable, plus a waiting-list landing mode for pre-launch ads.

## Requirements (hosting-agnostic)

- PHP **8.3+** (8.5 tested) with `pdo_mysql`, `curl`, `mbstring`
- MySQL **8+** (or MariaDB 10.6+)
- Apache/LiteSpeed (`.htaccess` shipped) or Nginx (replicate the denies below)
- No Composer, no Node — vendor-free by design

## Setup

```bash
cp .env.example .env      # fill in DB credentials, ADMIN_API_KEY, IP_HASH_SALT
# point the web root at this directory, then run migrations:
curl "https://YOUR-DOMAIN/api.php?action=runMigrations&key=$ADMIN_API_KEY"
```

Default admin login (change immediately in Vartotojai):
`admin@vertybes.local` / `pakeisk_mane_123`

Local development:

```bash
mysql -uroot -e "CREATE DATABASE vertybes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php -S 127.0.0.1:8080 router.php   # router = clean URLs like production
curl "http://127.0.0.1:8080/api.php?action=runMigrations&key=$ADMIN_API_KEY"
```

## Common operations

```bash
# Run migrations
curl "$URL/api.php?action=runMigrations&key=$ADMIN_API_KEY"
# Migration status
curl "$URL/api.php?action=migrationStatus&key=$ADMIN_API_KEY"
# DB info
curl "$URL/api.php?action=getDatabaseInfo&key=$ADMIN_API_KEY"
# Debug a table
curl "$URL/api.php?action=debugQuery&table=leads&key=$ADMIN_API_KEY"
# Export leads CSV
curl "$URL/api.php?action=exportLeadsCsv&key=$ADMIN_API_KEY" -o leads.csv
```

## Architecture

- **`api.php`** — every AJAX/CLI request goes through `?action=XXX`. Auth tiers:
  admin API key (`?key=` / `X-API-KEY`, CLI ops), session (admin portal humans,
  CSRF-protected mutations), and anonymous test sessions (uuid cookie `vt_session`).
- **`database.php`** — PDO wrapper (`Database`); use it everywhere, no raw PDO.
- **`migrations/`** — PHP classes `YYYY_MM_DD_NNNNNN_description.php` with
  `up($db)`/`down($db)`, run via the API (never directly).
- **`helpers/app.php`** — settings, UI texts (`t()`/`te()`), audit log, rate limit.
- **`includes/`** — admin layout partials (`head`/`sidebar`/`foot`) + public views.
- **`seeds/values_lt.csv`** — canonical 191-value catalog (imported by migration).
- **`js/utils.js`** — `apiCall()` with CSRF auto-retry; use it for all admin frontend calls.

### Content model (all editable in the admin panel)

| Table | What |
|---|---|
| `ui_texts` | Every user-facing string, keyed (`intro.title`, `compare.help`, …) |
| `questions` | The 4 open questions (text, hint, max answers) |
| `values_catalog` | 191 values: key, label, meaning, inner tension, synonyms |
| `settings` | Waitlist mode, booking URL, OpenAI key/model/prompt, versions |
| `leads` | Waiting-list + result email captures (CSV export in admin) |

### Waiting-list mode

While `waitlist_mode` setting is `1`, `index.php` serves the waiting-list
landing (email capture into `leads`). Switch to `0` in Nustatymai to serve the
test app at the same URL.

## Nginx notes

Deny web access to: `.env`, `*.log`, `config.php`, `database.php`, `auth.php`,
`logger.php`, `router.php`, and the directories `includes/`, `helpers/`,
`migrations/`, `scripts/`, `seeds/`, `logs/`.

Clean URLs (`/privatumas`, `/dashboard`, …) need:

```nginx
# 301 old .php URLs (original client requests only; api.php stays as-is)
if ($request_uri ~* "^/(login|logout|dashboard|leads|texts|questions|values|sessions|settings|users|privatumas|slapukai)\.php(\?.*)?$") {
    return 301 /$1$2;
}
location / { try_files $uri $uri/ $uri.php$is_args$args; }
```

Apache hosts get the same behaviour from the shipped `.htaccess`.

## DO NOT

- Commit `.env` or `logs/`.
- Skip CSRF for state-changing session actions.
- Add framework dependencies — vendor-free raw PHP by design.
- Call OpenAI from the browser — server-side only, key never leaves the backend.
