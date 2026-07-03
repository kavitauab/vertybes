<?php
/**
 * Migration: Seed default admin user, settings, UI texts and draft questions.
 *
 * All of this is editable later through the admin panel — the seed only
 * guarantees the app renders end-to-end after a fresh install.
 */

class SeedDefaultsMigration {
    public function up($db) {
        // ── Default admin (password must be changed after first login) ───────
        $exists = $db->fetchOne("SELECT id FROM users LIMIT 1");
        if (!$exists) {
            $db->insert('users', [
                'email' => 'admin@vertybes.local',
                'password_hash' => password_hash('pakeisk_mane_123', PASSWORD_DEFAULT),
                'name' => 'Administratorius',
                'role' => 'admin',
                'is_active' => 1,
            ]);
        }

        // ── Settings ──────────────────────────────────────────────────────────
        $settings = [
            'site_name'              => 'Vertybių testas',
            'waitlist_mode'          => '1',   // 1 = index.php shows the waiting list instead of the test
            'booking_url'            => 'https://booktomas.com',
            'openai_api_key'         => '',    // env OPENAI_API_KEY wins if set
            'openai_model'           => 'gpt-5.5',
            'ai_mock_mode'           => '1',   // 1 = keyword-based mock mapper (no API calls)
            'ai_prompt_version'      => 'v1',
            'ai_system_prompt'       => "Tu esi vertybių testo analizės variklis. Gauni lietuviškus atsakymus į atvirus klausimus ir KANONINĮ vertybių sąrašą (key, pavadinimas, sinonimai). Kiekvienam atsakymui priskirk VIENĄ tiksliausiai atitinkančią vertybę iš sąrašo. Naudok tik pateiktus value_key. Grąžink JSON pagal nurodytą schemą su confidence (0-1). Vertink turinį, ne paviršines frazes: jei atsakymas apie laisvę rinktis — 'laisve', jei apie ryšį su artimaisiais — 'artumas'.",
            'privacy_policy_version' => '2026-07-03',
            'cookie_policy_version'  => '2026-07-03',
            'min_distinct_values'    => '5',
            'answers_per_question_max' => '6',
        ];
        foreach ($settings as $k => $v) {
            $db->query(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_key = setting_key",
                [$k, $v]
            );
        }

        // ── UI texts (LT copy from spec + email thread; 'DRAFT' = needs Tomas) ─
        $texts = [
            // Intro
            ['intro.title',        'Vertybių testas', 'Intro ekrano antraštė'],
            ['intro.subtitle',     'Pamatyk, kurios vertybės realiai lemia tavo sprendimus.', 'Intro paantraštė'],
            ['intro.meta',         '4 klausimai. 5 atrinktos vertybės. 10 trumpų pasirinkimų. Apie 5 minutes.', 'Intro meta eilutė'],
            ['intro.cta',          'Pradėti', 'Intro mygtukas'],
            // Privacy / AI consent
            ['privacy.title',      'Prieš pradedant', 'Privatumo sutikimo antraštė'],
            ['privacy.body',       'Šiame teste tavo atsakymai bus analizuojami AI, kad galėtume pasiūlyti vertybes ir pateikti rezultatą.', 'Privatumo sutikimo tekstas'],
            ['privacy.confirm',    'Tęsdamas patvirtini, kad susipažinai su Privatumo politika.', 'Privatumo patvirtinimo eilutė'],
            ['privacy.link',       'Privatumo politika', 'Nuoroda į privatumo politiką'],
            ['privacy.yes',        'Taip', 'Sutikimo mygtukas'],
            ['privacy.no',         'Ne', 'Nesutikimo mygtukas'],
            ['privacy.declined',   'Be sutikimo testo pradėti negalime. Jei apsigalvosi — grįžk bet kada.', 'Rodoma paspaudus „Ne“'],
            // Cookies
            ['cookies.title',      'Slapukai', 'Slapukų sutikimo antraštė'],
            ['cookies.body',       'Kad testas veiktų, naudojame būtinus slapukus sesijai ir pasirinkimams išsaugoti. Be sutikimo tęsti negalėsi.', 'Slapukų sutikimo tekstas'],
            ['cookies.link',       'Slapukų politika', 'Nuoroda į slapukų politiką'],
            ['cookies.yes',        'Taip', 'Sutikimo mygtukas'],
            ['cookies.no',         'Ne', 'Nesutikimo mygtukas'],
            // Questions flow
            ['questions.progress', '{current} iš {total}', 'Klausimų progresas'],
            ['questions.answerPlaceholder', 'Įrašyk atsakymą…', 'Atsakymo laukelio placeholder'],
            ['questions.addAnswer','+ Pridėti atsakymą', 'Papildomo atsakymo mygtukas'],
            ['questions.needMore', 'Tavo atsakymuose radome mažiau nei 5 skirtingas vertybes. Papildyk atsakymus, kad rezultatas būtų tikslesnis.', 'Rodoma kai < 5 skirtingos vertybės'],
            // AI review
            ['values.review.title','Peržiūrėk vertybes', 'AI peržiūros antraštė'],
            ['values.review.help', 'Kiekvienam atsakymui AI priskyrė vertybę. Gali ją pakeisti.', 'AI peržiūros pagalba'],
            ['values.review.searchPlaceholder', 'Ieškok vertybės…', 'Vertybių paieškos placeholder'],
            ['values.review.loading', 'Analizuojame tavo atsakymus…', 'AI laukimo tekstas'],
            // Comparisons
            ['compare.intro',      'Toliau lyginsi 5 atrinktas vertybes. Bus 10 trumpų pasirinkimų.', 'Palyginimų įžanga'],
            ['compare.title',      'Kuri vertybė tau svarbesnė, jei turi rinktis?', 'Palyginimo klausimas'],
            ['compare.help',       'Jei abi svarbios, rinkis tą, kurią gintum labiau.', 'Palyginimo pagalba'],
            ['compare.progress',   'Palyginimas {current} iš {total}', 'Palyginimo progresas'],
            // Tie-breaker
            ['tiebreak.title',     'Lygiosios', 'Lygiųjų ekrano antraštė'],
            ['tiebreak.body',      'Šios dvi vertybės surinko tiek pat taškų. Reikia dar vieno pasirinkimo.', 'Lygiųjų paaiškinimas'],
            // Result
            ['result.title',       'Tavo stipriausios vertybės', 'Rezultato antraštė'],
            ['result.meaning',     'Ką tai reiškia', 'Reikšmės bloko antraštė'],
            ['result.tension',     'Galima vidinė įtampa', 'Įtampos bloko antraštė'],
            ['result.emailTitle',  'Gauti rezultatą el. paštu', 'El. pašto bloko antraštė'],
            ['result.emailPlaceholder', 'El. paštas', 'El. pašto placeholder'],
            ['result.emailSend',   'Siųsti', 'El. pašto mygtukas'],
            ['result.emailSaved',  'Ačiū! Rezultatą išsaugojome.', 'Po sėkmingo išsaugojimo'],
            ['result.emailConsent','Sutinku, kad mano el. paštas būtų naudojamas rezultatui atsiųsti.', 'El. pašto sutikimo eilutė'],
            ['result.cta',         'Rezervuoti pokalbį', 'Rezervacijos mygtukas'],
            // Waiting list (DRAFT — Tomas gali koreguoti admin panelėje)
            ['waitlist.title',     'Vertybių testas jau greitai', 'Laukimo sąrašo antraštė (DRAFT)'],
            ['waitlist.subtitle',  'Pamatyk, kurios vertybės realiai lemia tavo sprendimus. Palik el. paštą ir sužinok pirmas, kai testas startuos.', 'Laukimo sąrašo paantraštė (DRAFT)'],
            ['waitlist.meta',      '4 klausimai. 5 atrinktos vertybės. Apie 5 minutes.', 'Laukimo sąrašo meta (DRAFT)'],
            ['waitlist.emailPlaceholder', 'El. paštas', 'Laukimo sąrašo placeholder'],
            ['waitlist.cta',       'Pranešti man', 'Laukimo sąrašo mygtukas (DRAFT)'],
            ['waitlist.consent',   'Sutinku gauti pranešimą apie testo startą. Daugiau — Privatumo politikoje.', 'Laukimo sąrašo sutikimas (DRAFT)'],
            ['waitlist.success',   'Ačiū! Pranešime, kai testas startuos.', 'Po sėkmingos registracijos'],
            ['waitlist.duplicate', 'Šis el. paštas jau sąraše — ačiū!', 'Pakartotinė registracija'],
            // Common
            ['common.back',        'Atgal', 'Bendras mygtukas'],
            ['common.continue',    'Tęsti', 'Bendras mygtukas'],
            ['common.errorRequired','Norint tęsti, reikia sutikti.', 'Privalomo sutikimo klaida'],
            ['common.errorEmail',  'Įvesk galiojantį el. pašto adresą.', 'El. pašto validacijos klaida'],
            ['common.errorGeneric','Įvyko klaida. Bandyk dar kartą.', 'Bendra klaida'],
            // Policy pages (DRAFT legal copy — needs review before launch)
            ['privacy.page.title', 'Privatumo politika', 'Privatumo puslapio antraštė'],
            ['privacy.page.body',  "DRAFT — reikalinga teisinė peržiūra.\n\nŠis testas renka: tavo atsakymus į 4 klausimus, patvirtintas vertybes, palyginimų pasirinkimus ir (jei pats įvedi) el. pašto adresą. Atsakymai siunčiami AI paslaugų teikėjui (OpenAI) vertybėms pasiūlyti — jie nenaudojami AI modelių mokymui. IP adresas saugomas tik užšifruotas (hash). Duomenų valdytojas: Tomas Petrikaitis. Susisiek: tomas@petrikaitis.com dėl savo duomenų peržiūros ar ištrynimo.", 'Privatumo puslapio tekstas (DRAFT)'],
            ['cookies.page.title', 'Slapukų politika', 'Slapukų puslapio antraštė'],
            ['cookies.page.body',  "DRAFT — reikalinga teisinė peržiūra.\n\nNaudojame tik būtinuosius slapukus: sesijos identifikatorių, kuris leidžia išsaugoti tavo testo eigą. Analitikos ar rinkodaros slapukų nenaudojame.", 'Slapukų puslapio tekstas (DRAFT)'],
        ];
        foreach ($texts as [$key, $value, $context]) {
            $db->query(
                "INSERT INTO ui_texts (text_key, text_value, context) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE text_key = text_key",
                [$key, $value, $context]
            );
        }

        // ── Draft questions (FINAL TEXTS COME FROM FIGMA/TOMAS — edit in admin)
        $questions = [
            ['q1', 'Prisimink akimirką, kai jauteisi tikrai savimi. Kas tada vyko ir kodėl tai buvo svarbu?', 'Aprašyk situaciją keliais sakiniais arba atskirais punktais.'],
            ['q2', 'Kas tave labiausiai užgauna ar erzina kitų žmonių elgesyje?', 'Dažnai tai rodo pažeistą vertybę.'],
            ['q3', 'Dėl ko esi pasiruošęs (-usi) paaukoti savo laiką, pinigus ar patogumą?', 'Gali įrašyti kelis atsakymus.'],
            ['q4', 'Įsivaizduok savo idealią dieną po penkerių metų. Kas joje svarbiausia?', 'Kas turi būti toje dienoje, kad ji būtų prasminga?'],
        ];
        $order = 1;
        foreach ($questions as [$key, $text, $hint]) {
            $db->query(
                "INSERT INTO questions (question_key, text, hint, sort_order, max_answers, is_active)
                 VALUES (?, ?, ?, ?, 6, 1)
                 ON DUPLICATE KEY UPDATE question_key = question_key",
                [$key, $text, $hint, $order++]
            );
        }
    }

    public function down($db) {
        // Seeds are data only — dropping tables in the schema migration removes them.
    }
}
