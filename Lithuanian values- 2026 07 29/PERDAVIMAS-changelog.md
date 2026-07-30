# Pakeitimų žurnalas developeriui

## v4 (2026-07-29) — rezultato pabaigos pertvarka (breaking)

Keitėsi TIK srauto pabaiga (nuo rezultato ekrano). Klausimai, AI analizė ir dueliai nepakito.

- **Naujas srautas po duelių**: Rezultatas (8) → jei paliko el. paštą: 9a → 10; jei praleido: 9b → 10. IŠIMTI: atskiras „Išsiųsta" sėkmės puslapis ir el. pašto blokas „Kitas žingsnis" puslapyje
- **#8 Rezultatas = el. pašto vartai**: rodoma tik pagrindinė vertybė + viena STATINĖ eilutė „Šią vertybę tavo atsakymai paminėjo dažniausiai."; antroji vertybė, paaiškinimas ir įtampa NErodomi. Kortelė „Kai įrašysi el. paštą, atrakinsi" + ✓ Antrą tavo vertybę / ✓ Ką šis derinys reiškia kasdien / ✓ Kur šis derinys kelia įtampą + el. paštas + „Gauti rezultatą" + smulki nuoroda „Praleisti". Ženkliukas „Rezultatas paruoštas" (NE „baigtas"). VISION turinio čia nėra. Užrakintų/suliejamų kortelių nenaudojame sąmoningai (atrodo kaip paywall)
- **#9a Atrakinta**: „✓ Atrakinta" → pagrindinė vertybė → antroji vertybė → „Ką tai reiškia kasdien" → „Kur ši įtampa jau pasirodo" (atsidengia paeiliui) → CTA „Kas dar lemia sprendimus? →"
- **#9b Praleista**: pagrindinė vertybė + ta pati eilutė + priminimo kortelė „Jei persigalvosi, rezultatas vis dar laukia." su el. paštu, „Atrakinti" (veda į 9a) ir tais pačiais dviem sutikimais + Privatumo politika; „Toliau" veda į 10
- **#10 Kitas žingsnis = vienintelis bendras VISION puslapis** abiem keliams. Pradžia: „Puiku. Dabar žinai, kas tave labiausiai veda." → „Bet šis testas neatsako į vieną svarbų klausimą." → „Kodėl kartojasi tie patys sprendimai?" → Ko šis testas dar neparodo (3 punktai) → Kaip VISION metodas padeda → VISION CTA → sesijos nuoroda → Facebook → „See what matters."
- **Sutikimai**: visur, kur renkamas el. paštas (8 ir 9b), du atskiri sutikimai — privalomas rezultato ir pasirinktinis marketingo
- **GA4**: PRODUKCIJOJE TRŪKSTA `email_submit` (patikrinta 2026-07-29 — siunčiami tik view, question_answered, answer_add, pair_choice, cookie_accept). Nauji įvykiai: `gate_skip`, `unlock_view` (9a), `skip_view` (9b), `reminder_submit`, `next_step_click`; `email_submit` papildomas `entry_point: gate|reminder`

## v3 (2026-07-22)

Skirtumai nuo tavo turėtos versijos — eik punktais ir žymėkis, ką atnaujinai. Pilna galutinė specifikacija: PERDAVIMAS.md.

## Srautas (breaking)
- IŠIMTAS peržiūros ekranas („Peržiūrėk savo atsakymus") ir AI vertybių redagavimas. Naujas srautas: Landing → Consent → K1–K4 → AI analizė → Palyginimas → Dueliai → Rezultatas → Kitas žingsnis (email čia) → Išsiųsta. Q4 „Tęsti" → iškart analizė (be patvirtinimo), analizės ekrane ~2,8 s rodoma „✓ Atsakymai išsaugoti" + „Redaguoti atsakymus"
- Lyginamos TOP 5 vertybės (10 duelių; gali būti mažiau, jei vertybių <5); paskutinis duelis baigiasi „Štai ir viskas." kadru
- Klausimo ekranas: min 2 atsakymai (gate modalas nebe naudojamas), max 6; po 2 atsakymų automatiškai atsiranda 3 laukas; progresyvūs AI coaching pranešimai (🌱→🏔️, blunka į pilką helper); „✓ Atsakymas išsaugotas" blur-flash; auto-save visą laiką

## Ekranų dizainas/copy (visi galutiniai tekstai PERDAVIMAS.md ekranų sąraše)
- #1: antraštė „Kas iš tikrųjų lemia tavo sprendimus?", CTA „Atrasti savo vertybes", nauji bullet'ai, rūko hero
- #5: „Svarbiausios vertybės", viena kolona, kompaktiškos kortelės su mentions žetonais (be taškų), greita reveal animacija, CTA „Palyginti vertybes"
- #6: „Jei šiandien galėtum pasirinkti tik vieną…", skaitliukas „N / 10", be „arba", mentions ant kortelių
- #9 Kitas žingsnis: Vision hero, integruotas email blokas („Štai kas toliau"), DU sutikimai — privalomas (rezultatui) + pasirenkamas marketingo opt-in
- #10 Išsiųsta: kalno ženklas su ✓, rodomas realus įvestas el. paštas, stagger animacija, centruota; founder kortelės NĖRA
- #11: pilna 10 skyrių privatumo politika (2026-07-22, Boeder Equipments Limited, tomas@vertybes.lt, OpenAI įvardintas, saugojimo terminai)

## Analitika ir sekimas
- Įdiegta prototipe: GA4 G-GGS59F2SHD + Consent Mode v2, Meta Pixel 1012985031473778, MS Clarity xq3b5kvo6i (visi atrakinami tik po slapukų sutikimo)
- email_submit dabar neša marketing_opt_in; follow_click su source: next_step|sent; founder_story_click IŠIMTAS
- Atribucija: ?source=&referral_code= → localStorage vt_attribution + 90 d. cookie; referral_code TIKRINAMAS serveryje (coaches lentelė)

## Backend (naujas — /cloudflare aplankas)
- worker.js: /api/leads (D1-first, MailerLite su ml_pending retry) + /api/analyze (OpenAI gpt-4o-mini, griežtas JSON, be PII)
- GALUTINIS 32 vertybių žodynas (VALUES_DICT) — sinchronizuotas su PERDAVIMAS.md
- schema.sql (leads + coaches + answers), wrangler.toml; diegimas CLI arba dashboard (abu aprašyti)
- Žali atsakymai saugomi SQL atskirai (answers lentelė, session_id ryšys su lead; į MailerLite nesiunčiami; auto-trynimo NĖRA; gavus prašymą / unsubscribe lead ištrinamas, atsakymai lieka nuasmeninti statistikai — politikos §6)
- MailerLite: laukai value_1, value_2, lead_source (NE „source" — rezervuotas), referral_code, consent_version; dvi grupės; double opt-in OFF

## Assets
- assets/: favicon-32/192/512, apple-touch-icon, og-image 1200×630; OG/Twitter meta jau prototipo head'e
