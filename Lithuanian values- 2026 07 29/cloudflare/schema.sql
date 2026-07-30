-- Vertybės LT — D1 schema (wrangler d1 execute vertybes-db --file=cloudflare/schema.sql)
CREATE TABLE IF NOT EXISTS leads (
  id TEXT PRIMARY KEY,
  email TEXT UNIQUE NOT NULL,
  value_1 TEXT,
  value_2 TEXT,
  source TEXT,
  referral_code TEXT,
  referral_verified INTEGER DEFAULT 0,
  marketing_opt_in INTEGER DEFAULT 0,
  consent INTEGER NOT NULL DEFAULT 0,
  consent_version TEXT,
  consent_at TEXT,
  created_at TEXT,
  test_session_id TEXT,
  mailerlite_subscriber_id TEXT,
  ml_pending INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_leads_referral ON leads (referral_code);
CREATE INDEX IF NOT EXISTS idx_leads_ml_pending ON leads (ml_pending);

-- Žali atsakymai (pseudonimizuoti per session_id; į MailerLite NESIUNČIAMI)
-- Auto-trynimo šiame etape NĖRA (owner sprendimas 2026-07-22).
-- Ištrynimo prašymas / unsubscribe: ištrinti lead eilutę (nutrūksta ryšys su asmeniu);
-- answers lieka NUASMENINTI statistikai (session_id nieko nesieja su asmeniu) — politikos §6:
--   DELETE FROM leads WHERE email = ?;
CREATE TABLE IF NOT EXISTS answers (
  id TEXT PRIMARY KEY,
  session_id TEXT NOT NULL,
  question_number INTEGER,
  answer_text TEXT,
  created_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_answers_session ON answers (session_id);

CREATE TABLE IF NOT EXISTS coaches (
  code TEXT PRIMARY KEY,
  name TEXT,
  created_at TEXT DEFAULT (datetime('now'))
);
-- pvz.: INSERT INTO coaches (code, name) VALUES ('tomas123', 'Tomas');
