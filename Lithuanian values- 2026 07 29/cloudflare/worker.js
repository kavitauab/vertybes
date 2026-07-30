// Vertybės LT — Cloudflare Worker (API): /api/leads + /api/analyze
// Secrets: MAILERLITE_TOKEN, OPENAI_API_KEY (wrangler secret put ...)
// Vars (wrangler.toml): ALLOWED_ORIGIN, ML_GROUP_TEST, ML_GROUP_MARKETING
// D1 binding: DB (schema.sql)

const VALUES_DICT = ['Laisvė','Savarankiškumas','Drąsa','Nuotykiai','Smalsumas','Augimas','Meistrystė','Kūryba','Pasiekimai','Pripažinimas','Įtaka','Tiesa','Autentiškumas','Teisingumas','Atsakomybė','Pagarba','Saugumas','Ramybė','Sveikata','Harmonija','Disciplina','Artumas','Šeima','Bendruomenė','Empatija','Dosnumas','Prasmė','Dvasingumas','Tradicijos','Gamta','Grožis','Žaismingumas'];

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const cors = corsHeaders(env);
    if (request.method === 'OPTIONS') return new Response(null, { status: 204, headers: cors });
    try {
      if (url.pathname === '/api/leads' && request.method === 'POST') return await leads(request, env, cors);
      if (url.pathname === '/api/analyze' && request.method === 'POST') return await analyze(request, env, cors);
      return json({ error: 'not_found' }, 404, cors);
    } catch (e) {
      console.error(e);
      return json({ error: 'server_error' }, 500, cors);
    }
  }
};

function corsHeaders(env) {
  return {
    'Access-Control-Allow-Origin': env.ALLOWED_ORIGIN || 'https://vertybes.lt',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
    'Content-Type': 'application/json; charset=utf-8'
  };
}
const json = (data, status, headers) => new Response(JSON.stringify(data), { status, headers });

// ---------- /api/leads ----------
async function leads(request, env, cors) {
  const b = await request.json().catch(() => null);
  if (!b) return json({ error: 'bad_json' }, 400, cors);
  const email = String(b.email || '').trim().toLowerCase();
  if (!/^\S+@\S+\.\S+$/.test(email)) return json({ error: 'invalid_email' }, 422, cors);
  if (b.consent !== true) return json({ error: 'no_consent' }, 422, cors);

  const value1 = clean(b.value_1, 40), value2 = clean(b.value_2, 40);
  const source = clean(b.source, 40), ref = clean(b.referral_code, 60);
  const marketing = b.marketing_opt_in === true;
  const consentVersion = clean(b.consent_version, 20) || 'v1';

  // referral_code is client input — verify against the coaches table, never trust it
  let refVerified = 0;
  if (ref) {
    const row = await env.DB.prepare('SELECT 1 AS ok FROM coaches WHERE code = ?').bind(ref).first();
    refVerified = row ? 1 : 0;
  }

  const sessionId = clean(b.test_session_id, 64) || null;
  const id = crypto.randomUUID();
  // DB first — the lead must survive even if MailerLite is down
  await env.DB.prepare(
    `INSERT INTO leads (id, email, value_1, value_2, source, referral_code, referral_verified, marketing_opt_in, consent, consent_version, consent_at, created_at, ml_pending, test_session_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, datetime('now'), datetime('now'), 1)
     ON CONFLICT(email) DO UPDATE SET value_1=excluded.value_1, value_2=excluded.value_2, source=excluded.source,
       referral_code=excluded.referral_code, referral_verified=excluded.referral_verified,
       marketing_opt_in=excluded.marketing_opt_in, consent_version=excluded.consent_version,
       test_session_id=COALESCE(excluded.test_session_id, leads.test_session_id), ml_pending=1`
  ).bind(id, email, value1, value2, source, ref, refVerified, marketing ? 1 : 0, consentVersion).run();

  // MailerLite subscribe (transactional result email via group automation; marketing group only on opt-in)
  const groups = [env.ML_GROUP_TEST];
  if (marketing && env.ML_GROUP_MARKETING) groups.push(env.ML_GROUP_MARKETING);
  let mlOk = false;
  try {
    const r = await fetch('https://connect.mailerlite.com/api/subscribers', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${env.MAILERLITE_TOKEN}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        email,
        fields: { value_1: value1, value_2: value2, lead_source: source, referral_code: ref, consent_version: consentVersion },
        groups
      })
    });
    if (r.ok) {
      const data = await r.json();
      mlOk = true;
      await env.DB.prepare('UPDATE leads SET mailerlite_subscriber_id = ?, ml_pending = 0 WHERE email = ?')
        .bind(data?.data?.id || null, email).run();
    }
  } catch (e) { /* lead stays ml_pending=1; retry via cron */ }

  return json({ ok: true, ml: mlOk }, 200, cors); // user always gets success — the lead is safe in D1
}

const clean = (v, max) => String(v || '').trim().slice(0, max);

// ---------- /api/analyze ----------
async function analyze(request, env, cors) {
  const b = await request.json().catch(() => null);
  if (!b || !Array.isArray(b.answers) || b.answers.length === 0) return json({ error: 'bad_request' }, 400, cors);
  // PII never reaches OpenAI: only question_number + text
  const answers = b.answers.slice(0, 24).map(a => ({ question_number: Number(a.question_number) || 0, text: clean(a.text, 200) }));
  // Raw answers -> D1 (pseudonymous session_id, NOT sent to MailerLite; no auto-delete at MVP — removal only on erasure request / unsubscribe; policy §2/§6)
  const sessionId = clean(b.session_id, 64) || crypto.randomUUID();
  try {
    await env.DB.batch(answers.map(a => env.DB.prepare(
      "INSERT INTO answers (id, session_id, question_number, answer_text, created_at) VALUES (?, ?, ?, ?, datetime('now'))"
    ).bind(crypto.randomUUID(), sessionId, a.question_number, a.text)));
  } catch (e) { /* storage failure must not block analysis */ }

  const system = `Tu esi vertybių analizės variklis. Grąžink TIK griežtą JSON: {"values":[{"name":"...","confidence":0.0,"evidence":[{"quote":"...","question_number":1}],"mentions":["..."]}]}.
Taisyklės: (1) "name" TIK iš sąrašo: ${VALUES_DICT.join(', ')}. Sinonimus suvesk į kanoninį pavadinimą.
(2) Atrink 3–5 vertybes pagal: kryžminį dažnį tarp klausimų (svarbiausia), konkretumą (konkreti frazė > deklaracija), 2 klausimo (kas erzina) svorio daugiklį. Vertybė su viena bendrine citata nekvalifikuojama.
(3) "quote" — pažodinės citatos iš atsakymų. (4) "mentions" — 1–3 trumpos frazės vardininko forma, mažosiomis, ≤3 žodžiai, TIK iš tos vertybės citatų. (5) Jokio teksto už JSON ribų.`;

  for (let attempt = 0; attempt < 2; attempt++) {
    const r = await fetch('https://api.openai.com/v1/chat/completions', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${env.OPENAI_API_KEY}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: 'gpt-4o-mini',
        temperature: 0.2,
        response_format: { type: 'json_object' },
        messages: [
          { role: 'system', content: system },
          { role: 'user', content: JSON.stringify({ answers }) }
        ]
      })
    });
    if (!r.ok) continue;
    const data = await r.json();
    try {
      const out = JSON.parse(data.choices[0].message.content);
      const values = (out.values || [])
        .filter(v => VALUES_DICT.includes(v.name))
        .slice(0, 5)
        .map(v => ({
          name: v.name,
          confidence: Math.max(0, Math.min(1, Number(v.confidence) || 0)),
          evidence: (v.evidence || []).slice(0, 4),
          mentions: (v.mentions || []).slice(0, 3).map(m => clean(m, 24))
        }));
      if (values.length >= 3) return json({ values, session_id: sessionId }, 200, cors);
      if (values.length > 0 && attempt === 1) return json({ values, session_id: sessionId, note: 'fewer_than_3' }, 200, cors);
    } catch (e) { /* malformed JSON — retry once */ }
  }
  return json({ error: 'analysis_failed' }, 502, cors); // front-end shows the friendly retry state
}
